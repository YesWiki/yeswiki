<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;

use function Symfony\Component\String\u;

use YesWiki\Kernel\Service\StringUtilService;

/** A WikiName nothing else in the wiki holds. */
class WikiNameGenerator
{
    public function __construct(private readonly ContainerInterface $services)
    {
    }

    /**
     * @param string $name       the string to derive the name from
     * @param int    $occurrence recursion depth; 1 on the first call, 0 to skip the uniqueness
     *                           check entirely, and only >1 when retrying with a suffix
     */
    public function generate(string $name, int $occurrence = 1): string
    {
        if ($occurrence <= 1) {
            $name = u($name)->ascii();
            $temp = StringUtilService::withoutDiacritics(mb_substr(preg_replace('/[[:punct:]]/', ' ', $name), 0, 47, YW_CHARSET));
            $temp = explode(' ', ucwords(strtolower($temp)));
            $name = '';
            foreach ($temp as $mot) {
                $name .= (string)preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
            }

            $var = (string)preg_replace('/[^A-Z]/', '', $name);
            if (strlen($var) < 2) {
                $last = ucfirst(substr($name, strlen($name) - 1));
                $name = substr($name, 0, -1) . $last;
            }

            $name = '';
            foreach ($temp as $mot) {
                $name .= (string)preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
            }

            $var = (string)preg_replace('/[^A-Z]/', '', $name);
            if (strlen($var) < 2) {
                $last = ucfirst(substr($name, strlen($name) - 1));
                $name = substr($name, 0, -1) . $last;
            }
        } elseif ($occurrence > 2) {
            $nb = -1 * strlen(strval($occurrence - 1));
            $name = substr($name, 0, $nb) . $occurrence;
        } else {
            $name = $name . $occurrence;
        }

        if ($occurrence == 0) {
            return $name;
        } elseif (!is_array($this->services->get(PageManager::class)->getOne($name))) {
            return $name;
        }
        $occurrence++;

        return $this->generate($name, $occurrence);
    }
}
