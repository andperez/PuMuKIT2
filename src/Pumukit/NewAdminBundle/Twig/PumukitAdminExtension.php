<?php

namespace Pumukit\NewAdminBundle\Twig;

use Symfony\Component\Intl\Intl;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Pumukit\EncoderBundle\Services\ProfileService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\NewAdminBundle\Form\Type\Base\CustomLanguageType;

class PumukitAdminExtension extends \Twig_Extension
{
    private $dm;
    private $languages;
    private $profileService;
    private $translator;
    private $router;
    private $countMmobjsByStatus;
    private $countMmobjsWithTag;
    /**
     * Constructor.
     */
    public function __construct(ProfileService $profileService, DocumentManager $documentManager, TranslatorInterface $translator, RouterInterface $router)
    {
        $this->dm = $documentManager;
        $this->languages = Intl::getLanguageBundle()->getLanguageNames();
        $this->profileService = $profileService;
        $this->translator = $translator;
        $this->router = $router;
    }

    /**
     * Get name.
     */
    public function getName()
    {
        return 'pumukitadmin_extension';
    }

    /**
     * Get filters.
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('basename', array($this, 'getBasename')),
            new \Twig_SimpleFilter('profile', array($this, 'getProfile')),
            new \Twig_SimpleFilter('display', array($this, 'getDisplay')),
            new \Twig_SimpleFilter('duration_string', array($this, 'getDurationString')),
            new \Twig_SimpleFilter('language_name', array($this, 'getLanguageName')),
            new \Twig_SimpleFilter('status_icon', array($this, 'getStatusIcon')),
            new \Twig_SimpleFilter('status_text', array($this, 'getStatusText')),
            new \Twig_SimpleFilter('series_icon', array($this, 'getSeriesIcon')),
            new \Twig_SimpleFilter('series_text', array($this, 'getSeriesText')),
            new \Twig_SimpleFilter('profile_width', array($this, 'getProfileWidth')),
            new \Twig_SimpleFilter('profile_height', array($this, 'getProfileHeight')),
            new \Twig_SimpleFilter('series_announce_icon', array($this, 'getSeriesAnnounceIcon')),
            new \Twig_SimpleFilter('series_announce_text', array($this, 'getSeriesAnnounceText')),
            new \Twig_SimpleFilter('mms_announce_icon', array($this, 'getMmsAnnounceIcon')),
            new \Twig_SimpleFilter('mms_announce_text', array($this, 'getMmsAnnounceText')),
            new \Twig_SimpleFilter('filter_profiles', array($this, 'filterProfiles')),
            new \Twig_SimpleFilter('count_multimedia_objects', array($this, 'countMultimediaObjects')),
        );
    }

    /**
     * Get functions.
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('php_upload_max_filesize', array($this, 'getPhpUploadMaxFilesize')),
            new \Twig_SimpleFunction('path_exists', array($this, 'existsRoute')),
        );
    }

    /**
     * Get basename.
     *
     * @param string $path
     *
     * @return string
     */
    public function getBasename($path)
    {
        return basename($path);
    }

    /**
     * Get profile.
     *
     * @param Collection $tags
     *
     * @return string
     */
    public function getProfile($tags)
    {
        $profile = '';

        foreach ($tags as $tag) {
            if (false !== strpos($tag, 'profile:')) {
                return substr($tag, strlen('profile:'), strlen($tag) - 1);
            }
        }

        return $profile;
    }

    /**
     * Check if a route exists.
     *
     * @param string $name route name
     *
     * @return boolena
     */
    public function existsRoute($name)
    {
        return (null !== $this->router->getRouteCollection()->get($name));
    }

    /**
     * Get display.
     *
     * @param string $profileName
     *
     * @return string
     */
    public function getDisplay($profileName)
    {
        $display = false;
        $profile = $this->profileService->getProfile($profileName);

        if (null !== $profile) {
            $display = $profile['display'];
        }

        return $display;
    }

    /**
     * Get duration string.
     *
     * @param int $duration
     *
     * @return string
     */
    public function getDurationString($duration)
    {
        return gmdate('H:i:s', $duration);
    }

    /**
     * Get language name.
     *
     * @param string $code
     *
     * @return string
     */
    public function getLanguageName($code)
    {
        $addonLanguages = CustomLanguageType::$addonLanguages;

        if (isset($this->languages[$code])) {
            return ucfirst($this->languages[$code]);
        } elseif (isset($addonLanguages[$code])) {
            return ucfirst($this->translator->trans($addonLanguages[$code]));
        }

        return $code;
    }

    /**
     * Get status icon.
     *
     * @param int $status
     *
     * @return string
     */
    public function getStatusIcon($status)
    {
        $iconClass = 'mdi-alert-warning';

        switch ($status) {
            case MultimediaObject::STATUS_PUBLISHED:
                $iconClass = 'mdi-device-signal-wifi-4-bar';
                break;
            case MultimediaObject::STATUS_HIDE:
                $iconClass = 'mdi-device-signal-wifi-0-bar';
                break;
            case MultimediaObject::STATUS_BLOQ:
                $iconClass = 'mdi-device-wifi-lock';
                break;
        }

        return $iconClass;
    }

    /**
     * Get status text.
     *
     * @param int $status
     *
     * @return string
     */
    public function getStatusText($status)
    {
        $iconText = 'New';

        switch ($status) {
            case MultimediaObject::STATUS_PUBLISHED:
                $iconText = 'Published: is listed in the Series and can be played with published URL';
                break;
            case MultimediaObject::STATUS_HIDE:
                $iconText = 'Hidden: is not listed in the Series but can be played with magic URL';
                break;
            case MultimediaObject::STATUS_BLOQ:
                $iconText = 'Blocked: cannot be accessed outside the back-end';
                break;
        }

        return $iconText;
    }

    /**
     * Get series icon.
     *
     * @param string $series
     *
     * @return string
     */
    public function getSeriesIcon($series)
    {
        list($mmobjsPublished, $mmobjsHidden, $mmobjsBlocked) = $this->countMmobjsByStatus($series);

        $iconClass = 'mdi-alert-warning';

        if ((0 === $mmobjsPublished) && (0 === $mmobjsHidden) && (0 === $mmobjsBlocked)) {
            $iconClass = 'mdi-device-signal-wifi-off pumukit-none';
        } elseif (($mmobjsPublished > $mmobjsHidden) && ($mmobjsPublished > $mmobjsBlocked)) {
            $iconClass = 'mdi-device-signal-wifi-4-bar pumukit-published';
        } elseif (($mmobjsPublished === $mmobjsHidden) && ($mmobjsPublished > $mmobjsBlocked)) {
            $iconClass = 'mdi-device-signal-wifi-0-bar pumukit-hidden-published';
        } elseif (($mmobjsHidden > $mmobjsPublished) && ($mmobjsHidden > $mmobjsBlocked)) {
            $iconClass = 'mdi-device-signal-wifi-0-bar pumukit-hidden';
        } elseif (($mmobjsPublished === $mmobjsBlocked) && ($mmobjsPublished > $mmobjsHidden)) {
            $iconClass = 'mdi-device-wifi-lock pumukit-blocked-published';
        } elseif (($mmobjsBlocked === $mmobjsHidden) && ($mmobjsBlocked > $mmobjsPublished)) {
            $iconClass = 'mdi-device-wifi-lock pumukit-blocked-hidden';
        } elseif (($mmobjsPublished === $mmobjsBlocked) && ($mmobjsPublished === $mmobjsHidden)) {
            $iconClass = 'mdi-device-wifi-lock pumukit-blocked-hidden-published';
        } elseif (($mmobjsBlocked > $mmobjsPublished) && ($mmobjsBlocked > $mmobjsHidden)) {
            $iconClass = 'mdi-device-wifi-lock pumukit-blocked';
        }

        return $iconClass;
    }

    /**
     * Get series text.
     *
     * @param int $series
     *
     * @return string
     */
    public function getSeriesText($series)
    {
        list($mmobjsPublished, $mmobjsHidden, $mmobjsBlocked) = $this->countMmobjsByStatus($series);

        $iconText = $mmobjsPublished." Published Multimedia Object(s),\n".
                    $mmobjsHidden." Hidden Multimedia Object(s),\n".
                    $mmobjsBlocked." Blocked Multimedia Object(s)\n";

        return $iconText;
    }

    /**
     * Get track profile width resolution.
     *
     * @param Collection $tags
     *
     * @return string
     */
    public function getProfileWidth($tags)
    {
        $profileName = $this->getProfileFromTags($tags);
        $profile = $this->profileService->getProfile($profileName);
        if (null !== $profile) {
            return $profile['resolution_hor'];
        }

        return '0';
    }

    /**
     * Get track profile height resolution.
     *
     * @param Collection $tags
     *
     * @return string
     */
    public function getProfileHeight($tags)
    {
        $profileName = $this->getProfileFromTags($tags);
        $profile = $this->profileService->getProfile($profileName);
        if (null !== $profile) {
            return $profile['resolution_ver'];
        }

        return '0';
    }

    /**
     * Get announce icon of Series
     * and MultimediaObjects inside of it.
     *
     * @param Series $series
     *
     * @return string $icon
     */
    public function getSeriesAnnounceIcon($series)
    {
        $icon = 'mdi-action-done pumukit-transparent';

        if ($series->getAnnounce()) {
            return 'mdi-action-spellcheck pumukit-series-announce';
        }

        return $icon;
    }

    /**
     * Get announce text of Series
     * and MultimediaObjects inside of it.
     *
     * @param Series $series
     *
     * @return string $text
     */
    public function getSeriesAnnounceText($series)
    {
        $text = '';

        if ($series->getAnnounce()) {
            return 'This Series is announced';
        }

        return $text;
    }

    /**
     * Get announce icon of Multimedia Objects in Series
     * and MultimediaObjects inside of it.
     *
     * @param Series $series
     *
     * @return string $icon
     */
    public function getMmsAnnounceIcon($series)
    {
        $icon = 'mdi-action-done pumukit-transparent';

        $count = $this->countMmobjsWithTag($series, 'PUDENEW');

        if ($count > 0) {
            return 'mdi-action-spellcheck pumukit-mm-announce';
        }

        return $icon;
    }

    /**
     * Get announce text of Multimedia Objects in Series
     * and MultimediaObjects inside of it.
     *
     * @param Series $series
     *
     * @return string $text
     */
    public function getMmsAnnounceText($series)
    {
        $text = '';

        $count = $this->countMmobjsWithTag($series, 'PUDENEW');

        if ($count > 0) {
            return 'This Series has '.$count.' announced Multimedia Object(s)';
        }

        return $text;
    }

    /**
     * Get php upload max filesize.
     *
     * @return string
     */
    public function getPhpUploadMaxFilesize()
    {
        return ini_get('upload_max_filesize').'B';
    }

    /**
     * Get profile.
     *
     * @param Collection $tags
     *
     * @return string
     */
    private function getProfileFromTags($tags)
    {
        $profile = '';

        foreach ($tags as $tag) {
            if (false !== strpos($tag, 'profile:')) {
                return substr($tag, strlen('profile:'), strlen($tag) - 1);
            }
        }

        return $profile;
    }

    /**
     * Get duration in minutes
     * Returns duration file in minutes.
     *
     * @return int minutes
     */
    private function getDurationInMinutes($duration)
    {
        return floor($duration / 60);
    }

    /**
     * Get duration in seconds
     * Returns duration file in seconds.
     *
     * @return int seconds
     */
    private function getDurationInSeconds($duration)
    {
        $aux = $duration % 60;
        if ($aux < 10) {
            $aux = '0'.$aux;
        }

        return $aux;
    }

    /**
     * Filter profiles to show only audio profiles.
     *
     * @return array
     */
    public function filterProfiles($profiles, $onlyAudio)
    {
        return array_filter($profiles, function ($elem) use ($onlyAudio) { return !$onlyAudio || $elem['audio'];});
    }

    /**
     * Count Multimedia Objects.
     *
     * @param Series $series
     *
     * @return int
     */
    public function countMultimediaObjects($series)
    {
        return $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->countInSeries($series);
    }

    //TODO: Pass to a SERVICE
    private function countMmobjsByStatus($series)
    {
        if (isset($this->countMmobjsByStatus[$series->getId()])) {
            return $this->countMmobjsByStatus[$series->getId()];
        }
        $mmobjsPublished = 0;
        $mmobjsHidden = 0;
        $mmobjsBlocked = 0;

        $seriesColl = $this->dm->getDocumentCollection('PumukitSchemaBundle:MultimediaObject');
        $aggrPipe = array(
            array('$match' => array('series' => new \MongoId($series->getId()))),
            array('$group' => array('_id' => '$status',
                                    'count' => array('$sum' => 1), )),
        );
        $mmobjCounts = $seriesColl->aggregate($aggrPipe)->toArray();

        foreach ($mmobjCounts as $mmobjCount) {
            switch ($mmobjCount['_id']) {
                case MultimediaObject::STATUS_PUBLISHED:
                    $mmobjsPublished = $mmobjCount['count'];
                    break;
                case MultimediaObject::STATUS_HIDE:
                    $mmobjsHidden = $mmobjCount['count'];
                    break;
                case MultimediaObject::STATUS_BLOQ:
                    $mmobjsBlocked = $mmobjCount['count'];
                    break;
            }
        }

        $result = array($mmobjsPublished, $mmobjsHidden, $mmobjsBlocked);

        return $this->countMmobjsByStatus[$series->getId()] = $result;

        return $result;
    }

    //TODO: Pass to a SERVICE
    private function countMmobjsWithTag($series, $tagCod)
    {
        if (isset($this->countMmobjsWithTag[$series->getId()][$tagCod])) {
            return $this->countMmobjsWithTag[$series->getId()][$tagCod];
        }
        $repoSeries = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $qb = $repoSeries->createStandardQueryBuilder()->field('series')->equals(new \MongoId($series->getId()))->field('tags.cod')->equals('PUDENEW');
        $count = $qb->count()->getQuery()->execute();

        $this->countMmobjsWithTag[$series->getId()][$tagCod] = $count;
        return $count;
    }
}
