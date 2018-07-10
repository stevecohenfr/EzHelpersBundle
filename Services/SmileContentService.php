<?php
/**
 * Created by PhpStorm.
 * User: stcoh
 * Date: 03/11/17
 * Time: 11:47
 */

namespace Smile\EzHelpersBundle\Services;

use eZ\Publish\Core\SignalSlot\Repository;

class SmileContentService
{

    /** @var \eZ\Publish\Core\SignalSlot\Repository */
    protected $repository;

    protected $contentService;

    protected $locationService;

    protected $contentTypeService;

    protected $fieldTypeService;

    protected $searchService;

    public function  __construct(Repository $repository)
    {
        $this->repository = $repository;
        $this->contentService = $repository->getContentService();
        $this->locationService = $repository->getLocationService();
        $this->contentTypeService = $repository->getContentTypeService();
        $this->fieldTypeService = $repository->getFieldTypeService();
        $this->searchService = $repository->getSearchService();
    }

    public function getClassIdentifier( $content )
    {
        $contentType = $this->contentTypeService->loadContentType( $content->contentInfo->contentTypeId );
        return $contentType->identifier;
    }

    public function createContent($contentTypeidentifier, array $parentLocationIds, $fieldValues = array(), $lang = null)
    {
        if ($lang == null) {
            $lang = $this->repository->getContentLanguageService()->getDefaultLanguageCode();
        }
        $contentType = $this->contentTypeService->loadContentTypeByIdentifier($contentTypeidentifier);
        $contentCreateStruct = $this->contentService->newContentCreateStruct($contentType, $lang);

        $fields = $contentType->fieldDefinitions;
        foreach ($fields as $field) {
            $fieldIdentifier = $field->identifier;
            if (in_array($fieldIdentifier, $fieldValues)) {
                $contentCreateStruct->setField($fieldIdentifier, $fieldValues[$fieldIdentifier]);
            }
        }

        $locationCreateStructs = array();

        foreach ($parentLocationIds as $parentLocationId) {
            $locationCreateStructs[] = $this->locationService->newLocationCreateStruct($parentLocationId);
        }
        $draft = $this->contentService->createContent($contentCreateStruct, $locationCreateStructs);
        $content = $this->contentService->publishVersion($draft->versionInfo);

        return $content;
    }
}
