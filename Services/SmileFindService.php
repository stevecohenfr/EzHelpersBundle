<?php

/**
 * Smile helper to find content through eZPlatform API repository
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

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ConfigResolver;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use eZ\Publish\Core\SignalSlot\Repository;

/**
 * Class SmileFindService
 *
 * @category SmileService
 * @package  Smile\EzHelpersBundle\Services
 * @author   Steve Cohen <cohensteve@hotmail.fr>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     https://github.com/stevecohenfr/EzHelpersBundle Git of EzHelpersBundle
 */
class SmileFindService
{
    protected $repository;

    protected $configResolver;

    protected $contentService;

    protected $contentTypeService;

    protected $fieldTypeService;

    protected $searchService;

    protected $locationService;

    protected $smileConvertService;

    protected $urlAliasService;

    /**
     * SmileFindService constructor.
     *
     * @param Repository          $repository          The eZ Platform API Repository
     * @param ConfigResolver      $configResolver      The ConfigResolver
     * @param SmileConvertService $smileConvertService SmileConvertService
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function __construct(Repository $repository, ConfigResolver $configResolver, SmileConvertService $smileConvertService)
    {
        $this->repository = $repository;
        $this->configResolver = $configResolver;
        $this->contentService = $repository->getContentService();
        $this->contentTypeService = $repository->getContentTypeService();
        $this->fieldTypeService = $repository->getFieldTypeService();
        $this->searchService = $repository->getSearchService();
        $this->locationService = $repository->getLocationService();
        $this->smileConvertService = $smileConvertService;
        $this->urlAliasService = $repository->getURLAliasService();
    }

    /**
     * Find all children in the parent tree
     *
     * @param Content\Location $parentLocation        The parent location
     * @param String           $contentTypeIdentifier The content type identifier
     * @param int              $limit                 The limit
     * @param int              $offset                The offset
     * @param array            $customSortClauses     Custom sort clauses
     * @param array            $customCriteria        Custom criteria
     *
     * @return array
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findChildrenTree(Content\Location $parentLocation, $contentTypeIdentifier = null,
        $limit = 0, $offset = 0,  $customSortClauses = null, $customCriteria = null
    ) {
        $query = new LocationQuery();

        $criteria = array(
            new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            new Criterion\Subtree($parentLocation->pathString),
            new Criterion\LanguageCode($this->configResolver->getParameter('languages')[0])
        );
        if ($contentTypeIdentifier) {
            $criteria[] = new Criterion\ContentTypeIdentifier($contentTypeIdentifier);
        }
        if ($customCriteria ) {
            if (is_array($customCriteria)) {
                $criteria = array_merge($criteria, $customCriteria);
            } else {
                $criteria[] = $customCriteria;
            }
        }
        $query->filter = new Criterion\LogicalAnd($criteria);

        /* Sort Clauses */
        $sortClauses = array(
            new Content\Query\SortClause\Location\Priority(Content\Query::SORT_ASC)
        );
        if ($customSortClauses) {
            if (is_array($customSortClauses)) {
                $sortClauses = array_merge($customSortClauses, $sortClauses);
            } else {
                array_unshift($sortClauses, $customSortClauses);
            }
        }
        $query->sortClauses = $sortClauses;

        /* Limit and Offset */
        if ($limit > 0) {
            $query->limit = $limit;
        }
        if ($offset > 0) {
            $query->offset = $offset;
        }

        return $this->_prepareResults($this->searchService->findLocations($query));
    }

    /**
     * Count all the children in the parent tree
     *
     * @param Content\Location $parentLocation
     * @param null $contentTypeIdentifier
     * @param null $customCriteria
     *
     * @return int|null
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function countChildrenTree(Content\Location $parentLocation, $contentTypeIdentifier = null,
                                      $customCriteria = null) {
        $query = new LocationQuery();

        $criteria = array(
            new Criterion\Subtree($parentLocation->pathString),
            new Criterion\LogicalNot(new Criterion\LocationId($parentLocation->id))
        );
        if ($contentTypeIdentifier) {
            $criteria[] = new Criterion\ContentTypeIdentifier($contentTypeIdentifier);
        }
        if ($customCriteria) {
            if (is_array($customCriteria)) {
                $criteria = array_merge($criteria, $customCriteria);
            } else {
                $criteria[] = $customCriteria;
            }
        }
        $query->filter = new Criterion\LogicalAnd($criteria);

        $query->limit = 0;

        return $this->searchService->findLocations($query)->totalCount;
    }

    /**
     * Find all children in the first depth of the parent
     *
     * @param Content\Location $parentLocation        The parent location
     * @param null             $contentTypeIdentifier The content type identifier
     * @param int              $limit                 The limit
     * @param int              $offset                The offset
     * @param array            $customSortClauses     Custom sort clauses
     * @param array            $customCriteria        Custom criteria
     * @param bool             $queryOnly             Return LocationQuery instead of result
     *
     * @return Content\Location[]|LocationQuery
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findChildrenList(
        Content\Location $parentLocation,
        $contentTypeIdentifier = null,
        $limit = 0,
        $offset = 0,
        $customSortClauses = null,
        $customCriteria = null,
        $queryOnly = false
    ) {
        $query = new LocationQuery();

        /* Criteria */
        $criteria = array(
            new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            new Criterion\ParentLocationId($parentLocation->id),
            new Criterion\LanguageCode($this->configResolver->getParameter('languages')[0])
        );
        if ($contentTypeIdentifier ) {
            $criteria[] = new Criterion\ContentTypeIdentifier($contentTypeIdentifier);
        }
        if ($customCriteria ) {
            if (is_array($customCriteria)) {
                $criteria = array_merge($criteria, $customCriteria);
            } else {
                $criteria[] = $customCriteria;
            }
        }
        $query->filter = new Criterion\LogicalAnd($criteria);

        /* Sort Clauses */
        $sortClauses =  array(
            new Content\Query\SortClause\Location\Priority(Content\Query::SORT_DESC)
        );
        if ($customSortClauses) {
            if (is_array($customSortClauses)) {
                $sortClauses = array_merge($customSortClauses, $sortClauses);
            } else {
                array_unshift($sortClauses, $customSortClauses);
            }
        }
        $query->sortClauses = $sortClauses;

        /* Limit and Offset */
        if ($limit > 0 ) {
            $query->limit = $limit;
        }
        if ($offset > 0 ) {
            $query->offset = $offset;
        }
        if ($queryOnly) return $query;

        return $this->_prepareResults($this->searchService->findLocations($query));
    }

    /**
     * Only count children in the first parent depth
     *
     * @param Content\Location $parentLocation        The parent location
     * @param String           $contentTypeIdentifier The content type identifier
     * @param array            $customCriteria        Custom criteria
     *
     * @return int|null
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function countChildrenList(Content\Location $parentLocation, String $contentTypeIdentifier = null,
                                      $customCriteria = null)
    {
        $query = new LocationQuery();

        $criteria = array(
            new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            new Criterion\ParentLocationId($parentLocation->id),
            new Criterion\LanguageCode($this->configResolver->getParameter('languages')[0])
        );
        if ($contentTypeIdentifier ) {
            $criteria[] = new Criterion\ContentTypeIdentifier($contentTypeIdentifier);
        }
        if ($customCriteria ) {
            if (is_array($customCriteria)) {
                $criteria = array_merge($criteria, $customCriteria);
            } else {
                $criteria[] = $customCriteria;
            }
        }
        $query->limit = 0;

        $query->filter = new Criterion\LogicalAnd($criteria);

        return $this->searchService->findContent($query)->totalCount;
    }

    /**
     * Find all locations with an array of location id
     *
     * @param array $LocationIds All the location ids
     * @param bool  $allowHidden If true, it will also return the hidden locations
     *
     * @return array
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findByLocationIds(array $LocationIds, $allowHidden = false)
    {
        $query = new LocationQuery();

        $criteria = array(
            new Criterion\LocationId($LocationIds),
            new Criterion\LanguageCode($this->configResolver->getParameter('languages')[0])
        );

        if ($allowHidden == false) {
            $criteria[] = new Criterion\Visibility(Criterion\Visibility::VISIBLE);
        }

        $query->filter = new Criterion\LogicalAnd($criteria);

        $query->sortClauses = array(new Content\Query\SortClause\Location\Priority(Content\Query::SORT_ASC));

        return $this->_prepareResults($this->searchService->findLocations($query));
    }

    /**
     * Find all locations that match a content type contained in the given ContentTypeGroupe id
     *
     * @param Content\Location $parentLocation     The parent location
     * @param int              $classGroudId       The ContentTypeGroup id
     * @param array            $excludeContentType An array containing all ContentType identifier to exclude
     *
     * @return array
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findChildrenListByContentTypeGroupId(Content\Location $parentLocation, int $classGroudId,
        array $excludeContentType = null
    ) {
        $query = new LocationQuery();

        $criteria[] = new Criterion\LogicalAnd(
            array(
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\ParentLocationId($parentLocation->id),
                new Criterion\ContentTypeGroupId($classGroudId),
            )
        );
        if ($excludeContentType ) {
            $criteria[] = new Criterion\LogicalNot(
                new Criterion\ContentTypeIdentifier($excludeContentType)
            );
        }

        $query->filter = new Criterion\LogicalAnd($criteria);

        $query->sortClauses = array(new Content\Query\SortClause\Location\Priority(Content\Query::SORT_ASC));

        return $this->_prepareResults($this->searchService->findLocations($query));
    }

    /**
     * Find all the reverse relation locations of a content
     *
     * @param Content\Content $content               The content
     * @param array           $contentTypeIdentifier An array to filter relations by ContentType
     *
     * @return array|null
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findReverseRelationsByContentType(Content\Content $content, array $contentTypeIdentifier = array())
    {
        $reverseRelations = $this->contentService->loadReverseRelations($content->contentInfo);
        $relationsIds = array();

        if (count($reverseRelations) ) {
            foreach ($reverseRelations as $relation) {
                $contentInfo = $this->contentService->loadContentInfo($relation->sourceContentInfo->id);
                $contentType = $this->contentTypeService->loadContentType($contentInfo->contentTypeId);
                $identifier = $contentType->identifier;
                if (empty($contentType) || in_array($identifier, $contentTypeIdentifier)) {
                    $relationsIds[] = $relation->sourceContentInfo->mainLocationId;
                }
            }
            $result = $this->findByLocationIds($relationsIds);
        } else {
            $result = [];
        }

        return $result;
    }

    /**
     * Find all relation of a content
     *
     * @param Content\Content $content               The content
     * @param array           $contentTypeIdentifier An array to filter relations by ContentType
     *
     * @return array
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findRelationsByContentType(Content\Content $content, array $contentTypeIdentifier = array())
    {
        $relatedObjects = $this->contentService->loadRelations($content->versionInfo);
        $relations = array();
        if (count($relatedObjects) ) {
            foreach ($relatedObjects as $relation) {
                $contentInfo = $this->contentService->loadContentInfo($relation->destinationContentInfo->id);
                $contentType = $this->contentTypeService->loadContentType($contentInfo->contentTypeId);
                $identifier = $contentType->identifier;
                if (empty($contentType) || in_array($identifier, $contentTypeIdentifier) ) {
                    $relation = $this->locationService->loadLocation($relation->destinationContentInfo->mainLocationId);
                    if ($relation->invisible == false ) {
                        $relations[] = $relation;
                    }
                }
            }
        }
        return $relations;
    }

    /**
     * Return all relations of a content from a field name
     *
     * @param Content\Content $content   The content
     * @param String          $fieldName The relation field name
     *
     * @return array
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findRelationObjectsFromField(Content\Content $content, String $fieldName)
    {
        $destinationContentIds = $content->getField($fieldName)->value->destinationContentIds;
        $relatedObjects = array();
        foreach ($destinationContentIds as $id) {
            $relatedObjects[] = $this->contentService->loadContent($id);
        }
        return $relatedObjects;
    }

    /**
     * Return the content in the relation from the field name
     *
     * @param Content\Content $content   The content
     * @param String          $fieldName The relation field name
     *
     * @return Content\Content
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function findRelationObjectFromField(Content\Content $content, String $fieldName)
    {
        $destinationContentId = $content->getField($fieldName)->value->destinationContentId;
        $relatedObject = $this->contentService->loadContent($destinationContentId);

        return $relatedObject;
    }

    /**
     * Find the first parent of type matching the given ContentType identifier from the given location ancestors
     *
     * @param Content\Location $currentLocation   The location
     * @param String           $parentContentType The parent ContentType identifier you are looking for
     *
     * @return Content\Location|null
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findFirstParentOfType(Content\Location $currentLocation, String $parentContentType)
    {
        $parentLocation = $this->locationService->loadLocation($currentLocation->id);
        $contentIdentifier = $this->contentTypeService->loadContentType(
            $parentLocation->contentInfo->contentTypeId
        )->identifier;

        while ($contentIdentifier !== $parentContentType && $parentLocation->id != 2) {
            $parentLocation = $this->locationService->loadLocation($parentLocation->parentLocationId);
            $contentIdentifier = $this->contentTypeService->loadContentType(
                $parentLocation->contentInfo->contentTypeId
            )->identifier;
        }

        if ($parentLocation->id != 2) {
            return $parentLocation;
        }
        return null;
    }

    /**
     * Get the direct parent
     *
     * @param Content\Location $currentLocation The location
     *
     * @return Content\Location
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findFirstParent(Content\Location $currentLocation)
    {
        $parentLocation = $this->locationService->loadLocation($currentLocation->parentLocationId);

        return $parentLocation;
    }

    /**
     * Find the next content depending on the priority
     * <pre>
     * Example:
     * |- Foo (children ordered by priority)
     *    |- Bar (priority 10)
     *    |- Baz (priority 20)
     * </pre>
     * findNextContent(Bar) = Baz
     *
     * @param Content\Location $currentLocation       The location
     * @param String           $contentTypeIdentifier The ContentType identifier. If the next content does not match this identifier it will try with the next
     *
     * @return Content\Content|null
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findNextContent(Content\Location $currentLocation, String $contentTypeIdentifier = null)
    {
        $query = new Content\LocationQuery();

        $criteria = array(
            new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            new Criterion\ParentLocationId($currentLocation->parentLocationId),
            new Criterion\LanguageCode($this->configResolver->getParameter('languages')[0])
        );
        if ($contentTypeIdentifier ) {
            $criteria[] = new Criterion\ContentTypeIdentifier($contentTypeIdentifier);
        }
        $query->limit = 1;

        $criteria[] = new Criterion\Location\Priority(Operator::GT, $currentLocation->priority);

        $query->filter = new Criterion\LogicalAnd($criteria);

        $query->sortClauses = array(new Content\Query\SortClause\Location\Priority(Content\Query::SORT_ASC));

        $result = $this->_prepareResults($this->searchService->findLocations($query));
        if (count($result) > 0) {
            return $this->smileConvertService->locationToContent(
                $result[0]
            );
        }
        return null;
    }

    /**
     * Find the previous content depending on the priority
     * <pre>
     * Example:
     * |- Foo (children ordered by priority)
     *    |- Bar (priority 10)
     *    |- Baz (priority 20)
     * </pre>
     * findPreviousContent(Baz) = Bar
     *
     * @param Content\Location $currentLocation       The location
     * @param String           $contentTypeIdentifier The ContentType identifier. If the next content does not match this identifier it will try with the next
     *
     * @return Content\Content|null
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findPreviousContent(Content\Location $currentLocation, String $contentTypeIdentifier = null)
    {
        $query = new Content\LocationQuery();

        $criteria = array(
            new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            new Criterion\ParentLocationId($currentLocation->parentLocationId),
            new Criterion\LanguageCode($this->configResolver->getParameter('languages')[0])
        );
        if ($contentTypeIdentifier ) {
            $criteria[] = new Criterion\ContentTypeIdentifier($contentTypeIdentifier);
        }
        $query->limit = 1;

        $criteria[] = new Criterion\Location\Priority(Operator::LT, $currentLocation->priority);

        $query->filter = new Criterion\LogicalAnd($criteria);

        $query->sortClauses = array(new Content\Query\SortClause\Location\Priority(Content\Query::SORT_DESC));

        $result = $this->_prepareResults($this->searchService->findLocations($query));
        if (count($result) > 0) {
            return $this->smileConvertService->locationToContent(
                $result[0]
            );
        }
        return null;
    }

    /**
     * /!\ WIP /!\
     *
     * Find the index of the content depending on the sort chooser in backoffice for the parent
     *
     * The index start with 0
     * <pre>
     * Example:
     * |- Parent (children ordered by name)
     *    |- Location1
     *    |- Location2
     *    |- Location3
     * </pre>
     * findIndexInParent(Location2) = 1
     *
     * @param Content\Location $location The location
     *
     * @return int|null
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findIndexInParent(Content\Location $location)
    {
        $content = $this->contentService->loadContent($location->contentId);
        $query = new Content\LocationQuery();

        $parent = $this->locationService->loadLocation($location->parentLocationId);
        $sortField = $parent->sortField;
        $sortOrder = $parent->sortOrder;

        $querySortOrder = null;
        $fiterOperator = null;
        $sortClass = null;
        $criterion = null;

        $criteria = array(
            new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            new Criterion\ParentLocationId($location->parentLocationId),
            new Criterion\LanguageCode($this->configResolver->getParameter('languages')[0])
        );

        switch ($sortOrder) {
        case Content\Location::SORT_ORDER_ASC:
            $querySortOrder = Content\Query::SORT_ASC;
            $fiterOperator = Operator::LT;
            break;
        case Content\Location::SORT_ORDER_DESC:
            $querySortOrder = Content\Query::SORT_DESC;
            $fiterOperator = Operator::GT;
            break;
        default:
            $fiterOperator = Operator::LT;
        }

        switch ($sortField) {
        case Content\Location::SORT_FIELD_NAME:
            $sortClass = Content\Query\SortClause\ContentName::class;
            $criterion = new Criterion\Field('name', $fiterOperator, $content->contentInfo->name);
            //TODO NOT WORKING
            break;
        case Content\Location::SORT_FIELD_PATH:
            $sortClass = Content\Query\SortClause\Location\Path::class;
            //TODO
            break;
        case Content\Location::SORT_FIELD_PUBLISHED:
            $sortClass = Content\Query\SortClause\DatePublished::class;
            $criterion = new Criterion\DateMetadata(
                Criterion\DateMetadata::CREATED, $fiterOperator,
                $content->versionInfo->creationDate->getTimestamp()
            );
            break;
        case Content\Location::SORT_FIELD_MODIFIED:
            $sortClass = Content\Query\SortClause\DateModified::class;
            $criterion = new Criterion\DateMetadata(
                Criterion\DateMetadata::MODIFIED, $fiterOperator,
                $content->versionInfo->modificationDate->getTimestamp()
            );
            break;
        case Content\Location::SORT_FIELD_SECTION:
            $sortClass = Content\Query\SortClause\SectionName::class;
            //TODO
            break;
        case Content\Location::SORT_FIELD_DEPTH:
            $sortClass = Content\Query\SortClause\Location\Depth::class;
            $criterion = new Criterion\Location\Depth($fiterOperator, $location->depth);
            break;
        case Content\Location::SORT_FIELD_CLASS_IDENTIFIER:
            //TODO
            break;
        case Content\Location::SORT_FIELD_CLASS_NAME:
            //TODO
            break;
        default:
            $sortClass = Content\Query\SortClause\Location\Priority::class;
            $criterion = new Criterion\Location\Priority($fiterOperator, $location->priority);
        }

        $criteria[] = $criterion;

        $query->sortClauses = array(new $sortClass($querySortOrder));
        $query->filter = new Criterion\LogicalAnd($criteria);


        return $this->searchService->findLocations($query)->totalCount;
    }

    /**
     * Find a location matching the given URL (starting with a /)
     *
     * @param String $url          The URL you want to fetch the content (example: /foo/bar/baz)
     * @param String $languageCode The language code, if none given it will get the default language
     *
     * @return Content\Location|null
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findLocationByUrl(String $url, String $languageCode = null)
    {
        try {
            $urlAlias = $this->urlAliasService->lookup($url, $languageCode);
        }catch (NotFoundException $e) {
            return null;
        }
        return $this->locationService->loadLocation($urlAlias->destination);
    }

    /**
     * Try to get the location matching the given URL (starting with a /). If no location if found, it remove the last URL part and try again.
     * <pre>
     * Example:
     * |- Foo
     *    |- bar
     *       |- Baz
     *          | Qux
     * findFirstValidLocationByUrl('/Foo/Bar/quux/quuz') = Bar
     *
     * Explaination:
     * /Foo/Bar/quux/quuz = fail
     * /Foo/Bar/quux = fail
     * /Foo/Bar = success
     * </pre>
     *
     * @param String $url          The URL you want to fetch the content (example: /foo/bar/baz)
     * @param String $languageCode The language code, if none given it will get the default language
     *
     * @return Content\Location|null
     *
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function findFirstValidLocationByUrl(String $url, String $languageCode = null)
    {
        $urlArray = explode('/', $url);
        array_shift($urlArray); // Remove first /
        $foundLocation = null;
        while ($foundLocation == null && count($urlArray) > 0) {
            $foundLocation = $this->findLocationByUrl('/' . implode('/', $urlArray), $languageCode);
            array_pop($urlArray);
        }
        return $foundLocation;
    }

    /**
     * Find a child with its name in first depth
     *
     * @param Content\Location $parentLocation The parent location
     * @param String           $name           The name of the location you are looking for
     *
     * @return array An array with one or more location (if multiple locations with the same name)
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function findChildByNameInList(Content\Location $parentLocation, String $name)
    {
        $query = new LocationQuery();

        $query->filter = new Criterion\LogicalAnd(
            array(
                new Criterion\ParentLocationId($parentLocation->id),
                new Criterion\Field('name', Operator::EQ, $name),
            )
        );

        return $this->_prepareResults($this->searchService->findLocations($query));
    }

    /**
     * Find a child with its name in the subtree
     *
     * @param Content\Location $parentLocation The parent location of the subtree
     * @param String           $name           The name of the location you are looking for
     *
     * @return array An array with one or more location (if multiple locations with the same name)
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function findChildByNameInSubTree(Content\Location $parentLocation, String $name)
    {
        $query = new LocationQuery();

        $query->filter = new Criterion\LogicalAnd(
            array(
                new Criterion\Subtree($parentLocation->pathString),
                new Criterion\Field('name', Operator::EQ, $name),
            )
        );

        return $this->_prepareResults($this->searchService->findLocations($query));
    }

    /**
     * Get the root location of the current siteaccess depending on the siteaccess configuration in content.tree_root.location_id
     *
     * @return Content\Location
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function findRootLocationOfCurrentSiteaccess()
    {
        $locationid =  $this->configResolver->getParameter( 'content.tree_root.location_id' );

        return $this->findByLocationIds(array($locationid))[0];
    }

    /**
     * Transform searchHits in Location array
     *
     * @param Content\Search\SearchResult $results The fetch SearchResult
     *
     * @return array
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    private function _prepareResults(Content\Search\SearchResult $results)
    {
        $res = array();
        foreach ($results->searchHits as $hit) {
            $res[] = $hit->valueObject;
        }

        return $res;
    }
}
