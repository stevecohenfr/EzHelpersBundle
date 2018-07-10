<?php
/**
 * Created by PhpStorm.
 * User: stcoh
 * Date: 04/08/17
 * Time: 17:31
 */

namespace Smile\EzHelpersBundle\Services;

use eZ\Publish\Core\SignalSlot\Repository;

class SmileConvertService
{

    /** @var \eZ\Publish\Core\SignalSlot\Repository */
    protected $repository;

    /** @var SmileContentService   */
    protected $contentHelper;

    protected $contentService;

    protected $locationService;

    protected $contentTypeService;

    protected $fieldTypeService;

    protected $searchService;

    public function  __construct(Repository $repository, $contentHelper)
    {
        $this->repository = $repository;
        $this->contentService = $repository->getContentService();
        $this->locationService = $repository->getLocationService();
        $this->contentTypeService = $repository->getContentTypeService();
        $this->fieldTypeService = $repository->getFieldTypeService();
        $this->searchService = $repository->getSearchService();
        $this->contentHelper = $contentHelper;
    }

    public function locationToContent( $location )
    {
        $content = $this->contentService->loadContent( $location->contentId );
        return $content;
    }

    /**
     * @param $locationArray
     * @return \eZ\Publish\API\Repository\Values\Content\Content[]
     */
    public function locationArrayToContentArray( $locationArray )
    {
        $contentArray = array();
        if ( !empty($locationArray)) {
            foreach ($locationArray as $location) {
                $contentArray[] = $this->locationToContent($location);
            }
        }
        return $contentArray;
    }

    public function contentToMainLocation( $content )
    {
        $location = $this->locationService->loadLocation( $content->versionInfo->contentInfo->mainLocationId );
        return $location;
    }

    public function contentArrayToContentArrayByIdentifierKeys( $contentArray )
    {
        $arrayByIdentifierKeys = array();
        foreach ( $contentArray as $content )
        {
            $arrayByIdentifierKeys[$this->contentHelper->getClassIdentifier( $content ) ] = $content;
        }
        return $arrayByIdentifierKeys;
    }

}
