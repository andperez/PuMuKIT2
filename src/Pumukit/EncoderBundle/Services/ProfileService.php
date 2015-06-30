<?php

namespace Pumukit\EncoderBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\EncoderBundle\Document\Job;

class ProfileService
{
    private $dm;
    private $repo;
    private $profiles;

    const STREAMSERVER_STORE = 'store';
    const STREAMSERVER_DOWNLOAD = 'download';
    const STREAMSERVER_WMV = 'wmv';
    const STREAMSERVER_FMS = 'fms';
    const STREAMSERVER_RED5 = 'red5';

    /**
     * Constructor
     */
    public function __construct(array $profiles, DocumentManager $documentManager)
    {
        $this->dm = $documentManager;
        $this->repo = $this->dm->getRepository('PumukitEncoderBundle:Job');
        $this->profiles = $profiles;

        $this->validateProfilesDirOut();
    }

    /**
     * Get available profiles
     *
     * @param boolean|null $display if not null used to filter.
     * @param boolean|null $wizard if not null used to filter.
     * @param boolean|null $master if not null used to filter.
     * @return array filtered profiles
     */
    public function getProfiles($display = null, $wizard = null, $master = null)
    {
        if (is_null($display) && is_null($wizard) && is_null($master)) {
            return $this->profiles;
        }

        return array_filter($this->profiles, function ($profile) use ($display, $wizard, $master) {
            return ((is_null($display) || $profile['display'] === $display) &&
                    (is_null($wizard) || $profile['wizard'] === $wizard) &&
                    (is_null($master) || $profile['master'] === $master));
        });        

    }

    /**
     * Get master profiles
     *
     * @param boolean $master
     * @return array $profiles only master if true, only not master if false
     */
    public function getMasterProfiles($master)
    {
        return $this->getProfiles(null, null, $master);
    }

    /**
     * Get given profile
     * @param string the profile name (case sensitive)
     */
    public function getProfile($profile)
    {
        if (isset($this->profiles[$profile])){
            return $this->profiles[$profile];
        }

      return null;      
    }

    /**
     * Validate Profiles directories out
     */
    private function validateProfilesDirOut()
    {
        foreach($this->profiles as $profile){
            $dirOut = realpath($profile['streamserver']['dir_out']);
            if (!$dirOut){
                throw new \InvalidArgumentException("The path '".$profile['streamserver']['dir_out']."' for dir_out of the streamserver '".$profile['streamserver']['name']."' doesn't exist.");
            }
        }
    }
}