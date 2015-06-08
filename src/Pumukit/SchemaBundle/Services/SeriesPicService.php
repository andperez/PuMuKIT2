<?php

namespace Pumukit\SchemaBundle\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Pic;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Finder\Finder;

class SeriesPicService
{
    private $dm;
    private $repoMmobj;
    private $targetPath;
    private $targetUrl;
    private $forceDeleteOnDisk;

    public function __construct(DocumentManager $documentManager, $targetPath, $targetUrl, $forceDeleteOnDisk=true)
    {
        $this->dm = $documentManager;
        $this->targetPath = realpath($targetPath);
        if (!$this->targetPath){
            throw new \InvalidArgumentException("The path '".$targetPath."' for storing Pics does not exist.");
        }
        $this->targetUrl = $targetUrl;
        $this->repoMmobj = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->forceDeleteOnDisk = $forceDeleteOnDisk;
    }

  /**
   * Get pics from series or multimedia object
   */
  public function getRecommendedPics($series)
  {
      return $this->repoMmobj->findDistinctUrlPicsInSeries($series);
  }

  /**
   * Set a pic from an url into the series
   */
  public function addPicUrl(Series $series, $picUrl)
  {
      $pic = new Pic();
      $pic->setUrl($picUrl);

      $series->addPic($pic);
      $this->dm->persist($series);
      $this->dm->flush();

      return $series;
  }

  /**
   * Set a pic from an url into the series
   */
  public function addPicFile(Series $series, UploadedFile $picFile)
  {
      if(UPLOAD_ERR_OK != $picFile->getError()) {
          throw new \Exception($picFile->getErrorMessage());
      }

      if (!is_file($picFile->getPathname())) {
          throw new FileNotFoundException($picFile->getPathname());
      }

      $path = $picFile->move($this->targetPath."/".$series->getId(), $picFile->getClientOriginalName());

      $pic = new Pic();
      $pic->setUrl(str_replace($this->targetPath, $this->targetUrl, $path));
      $pic->setPath($path);

      $series->addPic($pic);
      $this->dm->persist($series);
      $this->dm->flush();

      return $series;
  }

    /**
     * Remove Pic from Series
     */
    public function removePicFromSeries(Series $series, $picId)
    {
        $pic = $series->getPicById($picId);
        $picPath = $pic->getPath();

        $series->removePicById($picId);
        $this->dm->persist($series);
        $this->dm->flush();

        if ($this->forceDeleteOnDisk && $picPath) {
            $this->deleteFileOnDisk($picPath, $series);
        }

        return $series;
    }

    private function deleteFileOnDisk($path, $series)
    {
        $dirname = pathinfo($path, PATHINFO_DIRNAME);
        try {
            $deleted = unlink($path);
            if (!$deleted) {
                throw new \Exception("Error deleting file '".$path."' on disk");
            }
            if (0 < strpos($dirname, $series->getId())) {
                $finder = new Finder();
                $finder->files()->in($dirname);
                if (0 === $finder->count()) {
                    $dirDeleted = rmdir($dirname);
                    if (!$deleted) {
                        throw new \Exception("Error deleting directory '".$dirname."'on disk");
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
