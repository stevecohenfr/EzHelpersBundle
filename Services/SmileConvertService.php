<?php

/**
 * Smile helper for to convert content
 *
 * PHP Version 7.1
 *
 * @category SmileService
 * @package  Smile\EzHelpersBundle\Services
 * @author   Steve Cohen <cohensteve@hotmail.fr>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     https://github.com/stevecohenfr/EzHelpersBundle Git of EzHelpersBundle
 */

namespace Smile\EzHelpersBundle\Services;

use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\Core\SignalSlot\Repository;

/**
 * Class SmileConvertService
 *
 * @category SmileService
 * @package  Smile\EzHelpersBundle\Services
 * @author   Steve Cohen <cohensteve@hotmail.fr>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     https://github.com/stevecohenfr/EzHelpersBundle Git of EzHelpersBundle
 */
class SmileConvertService
{

    protected $repository;

    protected $smileContentService;

    protected $contentService;

    protected $locationService;

    protected $contentTypeService;

    protected $fieldTypeService;

    protected $searchService;

    /**
     * SmileConvertService constructor.
     *
     * @param Repository          $repository          eZPlatform API Repository
     * @param SmileContentService $smileContentService The SmileContentService
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function __construct(Repository $repository, SmileContentService $smileContentService)
    {
        $this->repository = $repository;
        $this->contentService = $repository->getContentService();
        $this->locationService = $repository->getLocationService();
        $this->contentTypeService = $repository->getContentTypeService();
        $this->fieldTypeService = $repository->getFieldTypeService();
        $this->searchService = $repository->getSearchService();
        $this->smileContentService = $smileContentService;
    }

    /**
     * Get the location from the content
     *
     * @param Location $location The location you want the content
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function locationToContent(Location $location)
    {
        $content = $this->contentService->loadContent($location->contentId);
        return $content;
    }

    /**
     * Get a content array from a location array
     *
     * @param array $locationArray The location array you want the contents
     *
     * @return array
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function locationArrayToContentArray(array $locationArray)
    {
        $contentArray = array();
        if (!empty($locationArray)) {
            foreach ($locationArray as $location) {
                $contentArray[] = $this->locationToContent($location);
            }
        }
        return $contentArray;
    }

    /**
     * Get the main location from a content
     *
     * @param Content $content The content you want the main location
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Location
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function contentToMainLocation(Content $content)
    {
        $location = $this->locationService->loadLocation($content->versionInfo->contentInfo->mainLocationId);
        return $location;
    }

    /**
     * Organize all content by content identifier.
     *
     * @param array $contentArray An array of content you want to organize
     *
     * @return array An associative with content identifier as key and array of corresponding content as value
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function contentArrayToContentArrayByIdentifierKeys(array $contentArray)
    {
        $arrayByIdentifierKeys = array();
        foreach ($contentArray as $content) {
            $arrayByIdentifierKeys[$this->smileContentService->getClassIdentifier($content)][] = $content;
        }
        return $arrayByIdentifierKeys;
    }

}
