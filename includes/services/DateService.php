<?php

namespace YesWiki\Core\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Entity\Event;
use YesWiki\Core\Service\PageManager;

class DateService implements EventSubscriberInterface
{
    protected $entryManager;
    protected $followedIds;
    
    public static function getSubscribedEvents()
    {
        return [
            'entry.created' => 'followEntryChange',
            'entry.updated' => 'followEntryChange',
            'entry.deleted' => 'followEntryDeletion',
        ];
    }

    public function __construct(
        EntryManager $entryManager,
    ) {
        $this->entryManager = $entryManager;
        $this->followedIds = [];
    }


    public function getDateTimeWithRightTimeZone(string $date): DateTimeImmutable
    {
        $dateObj = new DateTimeImmutable($date);
        if (!$dateObj) {
            throw new Exception("date '$date' can not be converted to DateImmutable !");
        }
        // retrieve right TimeZone from parameters
        $defaultTimeZone = new DateTimeZone(date_default_timezone_get());
        if (!$defaultTimeZone) {
            $defaultTimeZone = new DateTimeZone('GMT');
        }
        $newDate = $dateObj->setTimeZone($defaultTimeZone);
        $anchor = '+00:00';
        if (substr($date, -strlen($anchor)) == $anchor) {
            // it could be an error
            $offsetToGmt = $defaultTimeZone->getOffset($newDate);
            // be careful to offset time because time is changed by setTimeZone
            $offSetAbs = abs($offsetToGmt);
            return ($offsetToGmt == 0)
            ? $newDate
            : (
                $offsetToGmt > 0
                ? $newDate->sub(new DateInterval("PT{$offSetAbs}S"))
                : $newDate->add(new DateInterval("PT{$offSetAbs}S"))
            );
        }
        return $newDate;
    }

    
    /**
     * @param Event $event
     */
    public function followEntryChange($event)
    {
        $entry = $this->getEntry($event);
        if ($this->shouldFollowEntry($entry)){
            $this->deleteLinkedEntries($entry);
            $this->createRepetitions($entry);
        }
    }

    /**
     * @param Event $event
     */
    public function followEntryDeletion($event)
    {
        $entryBeforeDeletion = $this->getEntry($event);
        if (!empty($entryBeforeDeletion)){
            $this->deleteLinkedEntries($entryBeforeDeletion);
        }
    }

    /**
     * @param string $entryId
     */
    public function followId(string $entryId)
    {
        if (!in_array($entryId,$this->followedIds)){
            $this->followedIds[] = $entryId;
        }
    }

    /**
     * @param Event $event
     * @return array $entry
     */
    protected function getEntry(Event $event): array
    {
        $data = $event->getData();
        $entry = $data['data'] ?? [];
        return is_array($entry) ? $entry : [];
    }

    /**
     * @param array $entry
     * @return bool
     */
    protected function shouldFollowEntry(array $entry): bool
    {
        return !empty($entry['id_fiche'])
            && in_array($entry['id_fiche'],$this->followedIds);
    }

    /**
     * get changes for repetition
     * @param array $entry
     */
    protected function createRepetitions(array $entry)
    {
        $extract = $this->checkData($entry);
        if (empty($extract)){
            return;
        }
        list(
            'data'=>$data,
            'currentStartDate'=>$currentStartDate,
            'currentEndDate'=>$currentEndDate
            ) = $extract;
        $step = intval($data['step']);
        $nbmax = intval($data['nbmax']);
        if ($nbmax > 50){
            $nbax = 50;
        }
        $newStartDate = DateTimeImmutable::createFromInterface($currentStartDate);
        $newEndDate = DateTimeImmutable::createFromInterface($currentEndDate);
        $days = $this->getDays($data);
        if (empty($days)){
            $days = [intval($newStartDate->format('N'))];
        }
        $months = $this->getMonths($data);
        if (empty($months)){
            $months = [intval($newStartDate->format('n'))];
        }
        for ($i=1; $i <= $nbmax; $i++) {
            $calculateNewStartDate = $newStartDate;
            switch ($data['repetition']) {
                case 'y':
                    $currentStartYear = intval($newStartDate->format('Y'));
                    $nextStartMonth = min($months);
                    $nextStartYear = $currentStartYear + $step;
                    $calculateNewStartDate= $this->findNextStartDate(
                        $newStartDate,
                        $calculateNewStartDate,
                        $data,
                        $days,
                        $nextStartYear,
                        $nextStartMonth,
                        function($month,&$year,$stepInternal){
                            $year = $year + $stepInternal;
                        }
                    );
                    break;
                case 'm':
                    $currentStartYear = intval($newStartDate->format('Y'));
                    $currentStartMonth = intval($newStartDate->format('n'));
                    $nextStartMonth = $currentStartMonth;
                    $this->calculateNextMonth($nextStartMonth,$currentStartYear,$step);
                    $calculateNewStartDate= $this->findNextStartDate(
                        $newStartDate,
                        $calculateNewStartDate,
                        $data,
                        $days,
                        $currentStartYear,
                        $nextStartMonth,
                        [$this,'calculateNextMonth']
                    );
                    break;
                case 'w':
                    $currentStartYear = intval($newStartDate->format('Y'));
                    $currentStartWeek = intval($newStartDate->format('W'));
                    $currentStartDay = intval($newStartDate->format('N'));
                    if (!in_array($currentStartDay,$days) || $currentStartDay === max($days)){
                        $nextWantedDay = min($days);
                        $nextStartWeek = $currentStartWeek + $step;
                        if ($nextStartWeek > 52){
                            $nextStartWeek = $nextStartWeek - 52;
                            $currentStartYear = $currentStartYear + 1;
                        }
                    } else {
                        $nextStartWeek = $currentStartWeek;
                        $nextWantedDay = min(
                            array_filter(
                                $days,
                                function ($day) use ($currentStartDay){
                                    return $day > $currentStartDay;
                                }
                            )
                        );
                    }
                    $calculateNewStartDate = $newStartDate->setISODate($currentStartYear,$nextStartWeek,$nextWantedDay);
                    break;
                case 'd':
                default:
                    $calculateNewStartDate = $newStartDate->add(new DateInterval("P{$step}D"));
                    break;
            }
            if (!empty($calculateNewStartDate) && $calculateNewStartDate->diff(new DateTimeImmutable('1970-01-01'))->invert === 1){
                $delta = $newStartDate->diff($calculateNewStartDate);
                $newStartDate = $calculateNewStartDate;
                $newEndDate= $newEndDate->add($delta);
                if (empty($data['limitdate']) || (
                    ($data['limitdate'])->diff($newEndDate)->invert == 1 && ($data['limitdate'])->diff($newStartDate)->invert == 1
                    )){
                    $newEntry = $entry;
                    $newEntry['id_fiche'] = $entry['id_fiche'].$newStartDate->format('Ymd');
                    foreach([
                        'bf_date_debut_evenement' => $newStartDate,
                        'bf_date_fin_evenement' => $newEndDate,
                    ] as $key => $dateObj){
                        if (strlen($entry[$key])>10){
                            $newEntry[$key] = $dateObj->format('c');
                        } else {
                            $newEntry[$key] = $dateObj->format('Y-m-d');
                        }
                    }
                    $newEntry['bf_date_fin_evenement_data'] = "{\"recurrentParentId\":\"{$entry['id_fiche']}\"}";
                    $newEntry['antispam'] = 1;
                    $this->entryManager->create(
                        $entry['id_typeannonce'],
                        $newEntry
                    );
                }
            }
        }
    }

    protected function calculateNextMonth(&$nextStartMonth,&$currentStartYear,$step)
    {
        $nextStartMonth = $nextStartMonth + $step;
        if ($nextStartMonth > 12){
            $nextStartMonth = $nextStartMonth - 12;
            $currentStartYear = $currentStartYear + 1;
        }
    }

    protected function findNextStartDate(
        DateTimeImmutable $calculateNewStartDate,
        DateTimeImmutable $newStartDate,
        array $data,
        array $days,
        int $currentStartYear,
        int $nextStartMonth,
        $callback): DateTimeImmutable
    {
        if ($data['whenInMonth'] === 'nthOfMonth'){
            $nth = intval($data['nth']);
            $limit = 60;
            while($limit > 0 && $nth > $this->getNbDaysInMonth($currentStartYear,$nextStartMonth)){
                $callback($nextStartMonth,$currentStartYear,$step);
                $limit = $limit -1;
            }
            $calculateNewStartDate = $newStartDate->setDate($currentStartYear,$nextStartMonth,$nth);
        } else {
            $wantedPositionList = [
                'fisrtOfMonth' => 1,
                'secondOfMonth' => 2,
                'thirdOfMonth' => 3,
                'forthOfMonth' => 4,
                'lastOfMonth' => 99
            ];
            $wantedPosition = $wantedPositionList[$data['whenInMonth']] ?? 1;
            $nbDaysInMonth = $this->getNbDaysInMonth($currentStartYear,$nextStartMonth);
            $day = min($days);
            $counter = 0;
            for ($j=1; $j < $nbDaysInMonth; $j++) {
                if ($counter < $wantedPosition){
                    $testedDate = $newStartDate->setDate($currentStartYear,$nextStartMonth,$j);
                    if (intval($testedDate->format('N')) === $day){
                        $counter = $counter + 1;
                        $calculateNewStartDate = $testedDate;
                    }
                }
            }
        }
        return $calculateNewStartDate;
    }

    protected function getNbDaysInMonth(int $year,int $month): int
    {
        return intval(
            (new DateTimeImmutable())->setDate($year,$month,1)->format('t')
        );
    }

    /**
     * check that data are rightly formatted
     * @param array $entry
     * @return array [$data,$currentStartDate,$currentEndDate]
     */
    protected function checkData(array $entry) : array
    {
        if(empty($entry['bf_date_fin_evenement_data'])
            || empty($entry['bf_date_fin_evenement'])
            || empty($entry['bf_date_debut_evenement'])){
            return [];
        }
        try {
            $currentStartDate = $this->getDateTimeWithRightTimeZone($entry['bf_date_debut_evenement']);
            $currentEndDate = $this->getDateTimeWithRightTimeZone($entry['bf_date_fin_evenement']);
        } catch (Throwable $th) {
            return [];
        }
        $data = $entry['bf_date_fin_evenement_data'];
        if (empty($data['isRecurrent']) || $data['isRecurrent'] !== '1'){
            return [];
        }
        // check repetition format
        if (empty($data['repetition']) || !in_array($data['repetition'],['d','w','m','y'],true)){
            return [];
        }
        if (in_array($data['repetition'],['m','y'],true) && (empty($data['whenInMonth']) || !is_string($data['whenInMonth']))){
            return [];
        }
        if (!empty($data['whenInMonth']) 
            && $data['whenInMonth'] === 'nthOfMonth' 
            && (
                empty($data['nth'])
                || !is_scalar($data['nth'])
                || intval($data['nth']) < 1
                || intval($data['nth']) > 31
            )){
            return [];
        }
        // check step format
        if (empty($data['step']) || !is_scalar($data['step']) || intval($data['step']) <= 0){
            return [];
        }
        // check nbmax format
        if (empty($data['nbmax']) || !is_scalar($data['nbmax']) || intval($data['nbmax']) <= 0){
            return [];
        }
        // check limitdate format
        if (!empty($data['limitdate'])){
            if(!is_string($data['limitdate'])){
                return [];
            }
            $dateTimeObj = new DateTimeImmutable($data['limitdate']);
            if (!$dateTimeObj){
                return [];
            }
            $data['limitdate'] = $dateTimeObj;
        }
        return compact(['data','currentStartDate','currentEndDate']);
    }

    protected function getDays(array $data): array
    {
        $days = [];
        $associations = [
            'mon' => 1,
            'tue' => 2,
            'wed' => 3,
            'thu' => 4,
            'fri' => 5,
            'sat' => 6,
            'sun' => 7
        ];
        if (!empty($data['days']) && is_array($data['days'])){
            foreach ($data['days'] as $name => $value) {
                if ($value === '1' && array_key_exists($name,$associations)){
                    $days[] = $associations[$name];
                }
            }
            sort($days);
        }
        return $days;
    }

    protected function getMonths(array $data): array
    {
        $months = [];
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
            'dec' => 12
        ];
        if (!empty($data['months']) && is_array($data['months'])){
            foreach ($data['months'] as $name => $value) {
                if ($value === '1' && array_key_exists($name,$associations)){
                    $months[] = $associations[$name];
                }
            }
            sort($months);
        }
        return $months;
    }

    /**
     * remove linked entries
     * @param array $entry
     */
    protected function deleteLinkedEntries(array $entry)
    {
        $entryId = $entry['id_fiche'];
        $formId = $entry['id_typeannonce'];
        $hasEndDateField = isset($entry['bf_date_fin_evenement']);
        if ($hasEndDateField && !empty($entryId) && !empty($formId)){
            $entriesToDelete = $this->entryManager->search([
                    'formsIds' => [$formId],
                    'queries' => [
                        'bf_date_fin_evenement_data' => "{\"recurrentParentId\":\"$entryId\"}"
                    ]
                ],
                true, // filter on read Acl
                false
            );
            foreach($entriesToDelete as $entryToDelete){
                try {
                    $this->entryManager->delete($entryToDelete['id_fiche']);
                } catch (Throwable $th) {
                    // do nothing
                }
            }
        }
    }
}
