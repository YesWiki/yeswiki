<?php

namespace YesWiki\Bazar\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;
use YesWiki\Core\Entity\Event;
use YesWiki\Core\Service\DateService as CoreDateService;
use YesWiki\Core\Service\PageManager;

class DateService implements EventSubscriberInterface
{
    protected const DEFAULT_MAXIMUM_REPETITION = 600;
    protected const PREFIX_ERROR = 'RecurentEvents: ';

    protected $coreDateService;
    protected $entryManager;
    protected $formManager;
    protected $followedIds;
    protected $pageManager;
    protected $params;
    protected $triggerError;

    public static function getSubscribedEvents()
    {
        return [
            'entry.created' => 'followEntryChange',
            'entry.updated' => 'followEntryChange',
            'entry.deleted' => 'followEntryDeletion',
        ];
    }

    public function __construct(
        CoreDateService $coreDateService,
        EntryManager $entryManager,
        FormManager $formManager,
        PageManager $pageManager,
        ParameterBagInterface $params
    ) {
        $this->coreDateService = $coreDateService;
        $this->entryManager = $entryManager;
        $this->followedIds = [];
        $this->formManager = $formManager;
        $this->pageManager = $pageManager;
        $this->params = $params;
        $this->triggerError = (
            $this->params->has('debug')
            && $this->params->get('debug') === 'yes'
        );
    }

    /**
     * @param Event $event
     */
    public function followEntryChange($event)
    {
        $entry = $this->getEntry($event);
        if ($this->shouldFollowEntry($entry)) {
            $this->deleteLinkedEntries($entry);
            if ($this->canRegisterMultipleEntries($entry)) {
                $this->createRepetitions($entry);
            }
        }
    }

    /**
     * @param Event $event
     */
    public function followEntryDeletion($event)
    {
        $entryBeforeDeletion = $this->getEntry($event);
        if (!empty($entryBeforeDeletion)) {
            $this->deleteLinkedEntries($entryBeforeDeletion);
        }
    }

    public function followId(string $entryId)
    {
        if (!in_array($entryId, $this->followedIds)) {
            $this->followedIds[] = $entryId;
        }
    }

    /**
     * @return array $entry
     */
    protected function getEntry(Event $event): array
    {
        $data = $event->getData();
        $entry = $data['data'] ?? [];

        return is_array($entry) ? $entry : [];
    }

    protected function shouldFollowEntry(array $entry): bool
    {
        return !empty($entry['id_fiche'])
            && in_array($entry['id_fiche'], $this->followedIds);
    }

    /**
     * get changes for repetition.
     */
    protected function createRepetitions(array $entry)
    {
        $extract = $this->checkData($entry);
        if (empty($extract)) {
            return;
        }
        try {
            list(
                'data' => $data,
                'currentStartDate' => $currentStartDate,
                'currentEndDate' => $currentEndDate
            ) = $extract;
            $step = intval($data['step']);
            $nbmax = intval($data['nbmax']);
            // if no limit date then choose default maximum repetition (to prevent DDoS)
            if (empty($data['limitdate']) || $nbmax > self::DEFAULT_MAXIMUM_REPETITION) {
                $nbax = self::DEFAULT_MAXIMUM_REPETITION;
            }
            $newStartDate = clone $currentStartDate;
            $newEndDate = clone $currentEndDate;
            $days = $this->getDays($data);
            if (empty($days)) {
                $days = [intval($newStartDate->format('N'))];
            }
            $selectedMonth = $this->getMonth($data);
            if (empty($selectedMonth)) {
                $selectedMonth = intval($newStartDate->format('n'));
            }
            for ($i = 1; $i <= $nbmax; $i++) {
                $calculateNewStartDate = $this->calculateNextDate(
                    $newStartDate,
                    $selectedMonth,
                    $days,
                    $step,
                    $data
                );
                if (!empty($calculateNewStartDate) && $calculateNewStartDate->diff(new DateTimeImmutable('1970-01-01'))->invert === 1) {
                    $delta = $newStartDate->diff($calculateNewStartDate);
                    if ($delta->invert === 1) {
                        throw new Exception('Error : calculated delta is negative for ' . "newStartDate:{$newStartDate->format('c')} and " . "calculateNewStartDate:{$calculateNewStartDate->format('c')}");
                    }
                    $newStartDate = $calculateNewStartDate;
                    $newEndDate = $newEndDate->add($delta);
                    if ($this->createEntryIfPossible($data, $newStartDate, $newEndDate, $entry)) {
                        // stop repetition when limit date is reached
                        break;
                    }
                }
            }
        } catch (Throwable $th) {
            $this->triggerNoticeErrorIfPossible("{$th->getMessage()} in file '" . basename($th->getFile()) . "' on line {$th->getLine()}");
        }
    }

    /**
     * create new entry if limit not reached.
     *
     * @param bool $limitDateIsReached
     */
    protected function createEntryIfPossible(
        array $data,
        DateTimeInterface $newStartDate,
        DateTimeInterface $newEndDate,
        array $entry
    ): bool {
        if (
            !empty($data['limitdate'])
            && (
                ($data['limitdate'])->diff($newEndDate)->invert == 0
                || ($data['limitdate'])->diff($newStartDate)->invert == 0
            )
        ) {
            return true;
        }
        if (
            empty($data['except'])
            || !in_array($newStartDate->format('Y-m-d'), $data['except'])
        ) {
            $newEntry = $entry;
            $newEntry['id_fiche'] = $entry['id_fiche'] . $newStartDate->format('Ymd');
            if (!empty($this->pageManager->getOne($newEntry['id_fiche']))) {
                $newName = genere_nom_wiki($newEntry['id_fiche']);
                if (empty($newName)) {
                    // does not create this repetition
                    $this->triggerNoticeErrorIfPossible("not possible to find an `id_fiche` from '{$newEntry['id_fiche']}'");

                    return false;
                }
                $newEntry['id_fiche'] = $newName;
            }
            foreach ([
                'bf_date_debut_evenement' => $newStartDate,
                'bf_date_fin_evenement' => $newEndDate,
            ] as $key => $dateObj) {
                if (strlen($entry[$key]) > 10) {
                    $newEntry[$key] = $dateObj->format('c');
                } else {
                    $newEntry[$key] = $dateObj->format('Y-m-d');
                }
            }
            $newEntry['bf_date_fin_evenement_data'] = "{\"recurrentParentId\":\"{$entry['id_fiche']}\"}";
            $newEntry['antispam'] = 1;
            $savedFiles = $_FILES;
            $_FILES = [];
            // to prevent ImageFile to badly update Image
            $this->entryManager->create(
                $entry['id_typeannonce'],
                $newEntry
            );
            $_FILES = $savedFiles;
        }

        return false;
    }

    /**
     * get calculateNewStartDate.
     *
     * @return DateTimeInterface $calculateNewStartDate
     */
    protected function calculateNextDate(
        DateTimeInterface $newStartDate,
        int $selectedMonth,
        array $days,
        int $step,
        array $data
    ): DateTimeInterface {
        switch ($data['repetition']) {
            case 'y':
                $currentStartYear = intval($newStartDate->format('Y'));
                $nextStartMonth = $selectedMonth;
                $nextStartYear = $currentStartYear + $step;
                $calculateNewStartDate = $this->findNextStartDate(
                    $newStartDate,
                    $data,
                    $days,
                    $nextStartYear,
                    $nextStartMonth,
                    function ($month, &$year, $stepInternal) {
                        $year = $year + $stepInternal;
                    }
                );
                break;
            case 'm':
                $currentStartYear = intval($newStartDate->format('Y'));
                $currentStartMonth = intval($newStartDate->format('n'));
                $nextStartMonth = $currentStartMonth;
                $this->calculateNextMonth($nextStartMonth, $currentStartYear, $step);
                $calculateNewStartDate = $this->findNextStartDate(
                    $newStartDate,
                    $data,
                    $days,
                    $currentStartYear,
                    $nextStartMonth,
                    [$this, 'calculateNextMonth']
                );
                break;
            case 'w':
                $currentStartYear = intval($newStartDate->format('o')); // ISO 8601 year
                $currentStartWeek = intval($newStartDate->format('W')); // ISO 8601 week
                $currentStartDay = intval($newStartDate->format('N'));
                if (!in_array($currentStartDay, $days) || $currentStartDay === max($days)) {
                    $nextWantedDay = min($days);
                    $tmpDate = $newStartDate->add(new DateInterval('P' . strval(7 * $step) . 'D'));
                    $currentStartYear = intval($tmpDate->format('o')); // ISO 8601 year
                    $nextStartWeek = intval($tmpDate->format('W')); // ISO 8601 week
                } else {
                    $nextStartWeek = $currentStartWeek;
                    $nextWantedDay = min(
                        array_filter(
                            $days,
                            function ($day) use ($currentStartDay) {
                                return $day > $currentStartDay;
                            }
                        )
                    );
                }
                $calculateNewStartDate = $newStartDate->setISODate($currentStartYear, $nextStartWeek, $nextWantedDay);
                break;
            case 'd':
            default:
                $calculateNewStartDate = $newStartDate->add(new DateInterval("P{$step}D"));
                break;
        }

        return $calculateNewStartDate;
    }

    protected function calculateNextMonth(&$nextStartMonth, &$currentStartYear, $step)
    {
        $nextStartMonth = $nextStartMonth + $step;
        if ($nextStartMonth > 12) {
            $nextStartMonth = $nextStartMonth - 12;
            $currentStartYear = $currentStartYear + 1;
        }
    }

    protected function findNextStartDate(
        DateTimeImmutable $newStartDate,
        array $data,
        array $days,
        int $currentStartYear,
        int $nextStartMonth,
        $callback
    ): DateTimeImmutable {
        $calculateNewStartDate = $newStartDate;
        if ($data['whenInMonth'] === 'nthOfMonth') {
            $nth = intval($data['nth']);
            $limit = 60;
            while ($limit > 0 && $nth > $this->getNbDaysInMonth($currentStartYear, $nextStartMonth)) {
                $callback($nextStartMonth, $currentStartYear, $step);
                $limit = $limit - 1;
            }
            $calculateNewStartDate = $newStartDate->setDate($currentStartYear, $nextStartMonth, $nth);
        } else {
            $wantedPositionList = [
                'fisrtOfMonth' => 1,
                'secondOfMonth' => 2,
                'thirdOfMonth' => 3,
                'forthOfMonth' => 4,
                'lastOfMonth' => 99,
            ];
            $wantedPosition = $wantedPositionList[$data['whenInMonth']] ?? 1;
            $nbDaysInMonth = $this->getNbDaysInMonth($currentStartYear, $nextStartMonth);
            $day = min($days);
            $counter = 0;
            for ($j = 1; $j < $nbDaysInMonth; $j++) {
                if ($counter < $wantedPosition) {
                    $testedDate = $newStartDate->setDate($currentStartYear, $nextStartMonth, $j);
                    if (intval($testedDate->format('N')) === $day) {
                        $counter = $counter + 1;
                        $calculateNewStartDate = $testedDate;
                    }
                }
            }
        }

        return $calculateNewStartDate;
    }

    protected function getNbDaysInMonth(int $year, int $month): int
    {
        return intval(
            (new DateTimeImmutable())->setDate($year, $month, 1)->format('t')
        );
    }

    /**
     * check that data are rightly formatted.
     *
     * @return array [$data,$currentStartDate,$currentEndDate]
     */
    protected function checkData(array $entry): array
    {
        if (empty($entry['bf_date_fin_evenement_data'])
            || empty($entry['bf_date_fin_evenement'])
            || empty($entry['bf_date_debut_evenement'])) {
            // this entry is not recurrent : return
            return [];
        }
        try {
            $currentStartDate = $this->coreDateService->getDateTimeWithRightTimeZone($entry['bf_date_debut_evenement']);
            $currentEndDate = $this->coreDateService->getDateTimeWithRightTimeZone($entry['bf_date_fin_evenement']);
        } catch (Throwable $th) {
            $this->triggerNoticeErrorIfPossible("for '{$entry['id_fiche']}', " .
                "{$th->getMessage()} from {$th->getFile()} on line {$th->getLine()}");

            return [];
        }
        $data = $entry['bf_date_fin_evenement_data'];
        if (empty($data['isRecurrent']) || $data['isRecurrent'] !== '1') {
            return [];
        }
        // check repetition format
        if (empty($data['repetition']) || !in_array($data['repetition'], ['d', 'w', 'm', 'y'], true)) {
            $this->triggerCheckDataErrorIfPossible($entry, 'repetition');

            return [];
        }
        if (in_array($data['repetition'], ['m', 'y'], true) && (empty($data['whenInMonth']) || !is_string($data['whenInMonth']))) {
            $this->triggerCheckDataErrorIfPossible($entry, 'whenInMonth');

            return [];
        }
        if (!empty($data['whenInMonth'])
            && $data['whenInMonth'] === 'nthOfMonth'
            && (
                empty($data['nth'])
                || !is_scalar($data['nth'])
                || intval($data['nth']) < 1
                || intval($data['nth']) > 31
            )) {
            $this->triggerCheckDataErrorIfPossible($entry, 'nth');

            return [];
        }
        // check step format
        if (empty($data['step']) || !is_scalar($data['step']) || intval($data['step']) <= 0) {
            $this->triggerCheckDataErrorIfPossible($entry, 'step');

            return [];
        }
        // check nbmax format
        if (empty($data['nbmax']) || !is_scalar($data['nbmax']) || intval($data['nbmax']) <= 0) {
            $this->triggerCheckDataErrorIfPossible($entry, 'nbmax');

            return [];
        }
        // check limitdate format
        if (!empty($data['limitdate'])) {
            if (!is_string($data['limitdate'])) {
                $this->triggerCheckDataErrorIfPossible($entry, 'limitdate');

                return [];
            }
            $dateTimeObj = new DateTimeImmutable($data['limitdate']);
            if (!$dateTimeObj) {
                $this->triggerCheckDataErrorIfPossible($entry, 'limitdate');

                return [];
            }
            $data['limitdate'] = $dateTimeObj;
        }
        // check except
        if (!empty($data['except'])) {
            if (!is_array($data['except'])) {
                $this->triggerCheckDataErrorIfPossible($entry, 'except');

                return [];
            }
            $data['except'] = array_map(
                function ($value) {
                    return is_string($value)
                        ? new DateTimeImmutable($value)
                        : null;
                },
                $data['except']
            );
            $data['except'] = array_filter(
                $data['except'],
                function ($value) {
                    return !empty($value);
                }
            );
            $data['except'] = array_map(
                function ($dateObj) {
                    return $dateObj->format('Y-m-d');
                },
                $data['except']
            );
        }

        return compact(['data', 'currentStartDate', 'currentEndDate']);
    }

    /**
     * trigger notice error if possible forCheckData.
     */
    protected function triggerCheckDataErrorIfPossible(array $entry, string $paramName)
    {
        if ($this->triggerError && !empty($entry) && !empty($paramName)) {
            $this->triggerNoticeErrorIfPossible("::checkData for {$entry['id_fiche']}, '\$data['$paramName']' is not correct !");
        }
    }

    /**
     * trigger notice error if possible.
     */
    protected function triggerNoticeErrorIfPossible(string $message)
    {
        if (!empty($message) && $this->triggerError) {
            trigger_error(self::PREFIX_ERROR . $message);
        }
    }

    protected function getDays(array $data): array
    {
        $days = (!empty($data['days']) && is_array($data['days']))
            ? $data['days']
            : [];
        $associations = [
            'mon' => 1,
            'tue' => 2,
            'wed' => 3,
            'thu' => 4,
            'fri' => 5,
            'sat' => 6,
            'sun' => 7,
        ];
        $days = array_filter($days, function ($name) use ($associations) {
            return is_string($name) && array_key_exists($name, $associations);
        });
        $days = array_map(function ($name) use ($associations) {
            return $associations[$name];
        }, $days);
        sort($days);

        return $days;
    }

    protected function getMonth(array $data): string
    {
        $associations = [
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'may' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'oct' => 10,
            'nov' => 11,
            'dec' => 12,
        ];

        return (!empty($data['month']) && array_key_exists($data['month'], $associations))
            ? $associations[$data['month']]
            : '';
    }

    /**
     * remove linked entries.
     */
    protected function deleteLinkedEntries(array $entry)
    {
        $entryId = $entry['id_fiche'];
        $formId = $entry['id_typeannonce'];
        $hasEndDateField = isset($entry['bf_date_fin_evenement']);
        if ($hasEndDateField && !empty($entryId) && !empty($formId)) {
            $entriesToDelete = $this->entryManager->search(
                [
                    'formsIds' => [$formId],
                    'queries' => [
                        'bf_date_fin_evenement_data' => ".*$entryId.*",
                    ],
                ],
                false, // filter on read Acl
                false
            );
            if (is_iterable($entriesToDelete)) {
                $entriesToDelete = array_filter(
                    $entriesToDelete,
                    function ($entryToFilter) use ($entryId) {
                        return !empty($entryToFilter['bf_date_fin_evenement_data'])
                            && $entryToFilter['bf_date_fin_evenement_data'] === "{\"recurrentParentId\":\"$entryId\"}";
                    }
                );
                foreach ($entriesToDelete as $entryToDelete) {
                    try {
                        $this->entryManager->delete($entryToDelete['id_fiche'], true); // $forceEvenIfNotOwner = true
                    } catch (Throwable $th) {
                        // do nothing
                    }
                }
            }
        }
    }

    /**
     * check if associated form is restricted for only one entry by user.
     */
    public function canRegisterMultipleEntries(?array $entry): bool
    {
        // default true
        $canRegisterMultipleEntries = true;
        if (!empty($entry['id_typeannonce']) && is_scalar($entry['id_typeannonce'])) {
            $form = $this->formManager->getOne(strval($entry['id_typeannonce']));
            if (!empty($form['bn_only_one_entry'])) {
                $canRegisterMultipleEntries = ($form['bn_only_one_entry'] !== 'Y');
            }
        }

        return $canRegisterMultipleEntries;
    }
}
