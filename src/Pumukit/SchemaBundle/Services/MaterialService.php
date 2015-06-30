<?php

namespace Pumukit\SchemaBundle\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Material;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Finder\Finder;

class MaterialService
{
    private $dm;
    private $targetPath;
    private $targetUrl;
    private $forceDeleteOnDisk;

    public function __construct(DocumentManager $documentManager, $targetPath, $targetUrl, $forceDeleteOnDisk=true)
    {
        $this->dm = $documentManager;
        $this->targetPath = realpath($targetPath);
        if (!$this->targetPath){
            throw new \InvalidArgumentException("The path '".$targetPath."' for storing Materials does not exist.");
        }
        $this->targetUrl = $targetUrl;
        $this->forceDeleteOnDisk = $forceDeleteOnDisk;
    }

    /**
     * Update Material in Multimedia Object
     */
    public function updateMaterialInMultimediaObject(MultimediaObject $multimediaObject)
    {
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Set a material from an url into the multimediaObject
     */
    public function addMaterialUrl(MultimediaObject $multimediaObject, $url, $formData)
    {
        $material = new Material();
        $material = $this->saveFormData($material, $formData);

        $material->setUrl($url);

        $multimediaObject->addMaterial($material);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Add a material from a file into the multimediaObject
     */
    public function addMaterialFile(MultimediaObject $multimediaObject, UploadedFile $materialFile, $formData)
    {

        if(UPLOAD_ERR_OK != $materialFile->getError()) {
           throw new \Exception($materialFile->getErrorMessage());
        }

        if (!is_file($materialFile->getPathname())) {
            throw new FileNotFoundException($materialFile->getPathname());
        }

        $material = new Material();
        $material = $this->saveFormData($material, $formData);

        $path = $materialFile->move($this->targetPath."/".$multimediaObject->getId(), $materialFile->getClientOriginalName());

        $material->setPath($path);
        $material->setUrl(str_replace($this->targetPath, $this->targetUrl, $path));

        $multimediaObject->addMaterial($material);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Remove Material from Multimedia Object
     */
    public function removeMaterialFromMultimediaObject(MultimediaObject $multimediaObject, $materialId)
    {
        $material = $multimediaObject->getMaterialById($materialId);
        $materialPath = $material->getPath();

        $multimediaObject->removeMaterialById($materialId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        if ($this->forceDeleteOnDisk && $materialPath) {
            $this->deleteFileOnDisk($materialPath);
        }

        return $multimediaObject;
    }

    /**
     * Up Material in Multimedia Object
     */
    public function upMaterialInMultimediaObject(MultimediaObject $multimediaObject, $materialId)
    {
        $multimediaObject->upMaterialById($materialId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Down Material in Multimedia Object
     */
    public function downMaterialInMultimediaObject(MultimediaObject $multimediaObject, $materialId)
    {
        $multimediaObject->downMaterialById($materialId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Save form data of Material
     *
     * @return Material $material
     */
    private function saveFormData(Material $material, $formData)
    {
        if (array_key_exists('i18n_name', $formData)) {
            $material->setI18nName($formData['i18n_name']);
        }
        if (array_key_exists('hide', $formData)) {
            $material->setHide($formData['hide']);
        }
        if (array_key_exists('mime_type', $formData)) {
            $material->setMimeType($formData['mime_type']);
        }

        return $material;
    }

    private function deleteFileOnDisk($path)
    {
        $dirname = pathinfo($path, PATHINFO_DIRNAME);
        try {
            $deleted = unlink($path);
            if (!$deleted) {
                throw new \Exception("Error deleting file '".$path."' on disk");
            }
            $finder = new Finder();
            $finder->files()->in($dirname);
            if (0 === $finder->count()) {
                $dirDeleted = rmdir($dirname);
                if (!$deleted) {
                    throw new \Exception("Error deleting directory '".$dirname."'on disk");
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
