<?php
/**
 * Created by PhpStorm.
 * User: stcoh
 * Date: 26/07/17
 * Time: 16:25
 */

namespace Smile\EzHelpersBundle\Services;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ConfigResolver;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use eZ\Publish\Core\SignalSlot\Repository;

class SmileFindService
{
    /** @var \eZ\Publish\Core\SignalSlot\Repository */
    protected $repository;

    protected $configResolver;

    protected $contentService;

    protected $contentTypeService;

    protected $fieldTypeService;

    protected $searchService;

    protected $locationService;

    /** @var SmileConvertService */
    protected $smileConvertService;

    protected $urlAliasService;

    public function  __construct(Repository $repository, ConfigResolver $configResolver, SmileConvertService $smileConvertService)
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
     * Récupère tous les enfants dans l'arborescence
     * @param  $parentLocation
     * @param array $contentTypeIdentifier
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findChildrenTree(Content\Location $parentLocation, $contentTypeIdentifier = null, $limit = -1, $offset = -1)
    {
        $query = new LocationQuery();


        $criteria = array(
            new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
            new Criterion\Subtree( $parentLocation->pathString ),
            new Criterion\LanguageCode( $this->configResolver->getParameter( 'languages' )[0] )
        );
        if ( $contentTypeIdentifier )
        {
            $criteria[] = new Criterion\ContentTypeIdentifier( $contentTypeIdentifier );
        }
        if ( $limit > 0 )
        {
            $query->limit = $limit;
        }
        if ( $offset > 0 )
        {
            $query->offset = $offset;
        }
        $query->filter = new Criterion\LogicalAnd( $criteria );
        $query->sortClauses = array( new Content\Query\SortClause\Location\Priority( Content\Query::SORT_ASC ) );

        return $this->prepareResults( $this->searchService->findLocations( $query ) );
    }

    /**
     * Liste les enfants triés par priorités définies dans le parent
     *
     * @param Content\Location $parentLocation
     * @param array|string|null $contentTypeIdentifier
     * @param int $limit
     * @param int $offset
     * @return Content\Location[]
     * @throws \eZ\Publish\API\Repository\Exceptions\NotImplementedException
     */
    public function findChildrenList(Content\Location $parentLocation, $contentTypeIdentifier = null, $limit = 0, $offset = 0, $customSortClauses = null, $customCriteria = null)
    {
        $query = new LocationQuery();

        /* Criteria */
        $criteria = array(
            new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
            new Criterion\ParentLocationId( $parentLocation->id ),
            new Criterion\LanguageCode( $this->configResolver->getParameter( 'languages' )[0] )
        );
        if ( $contentTypeIdentifier ) {
            $criteria[] = new Criterion\ContentTypeIdentifier( $contentTypeIdentifier );
        }
        if ( $customCriteria ) {
            if (is_array($customCriteria)) {
                $criteria = array_merge($criteria, $customCriteria);
            }else {
                $criteria[] = $customCriteria;
            }
        }
        $query->filter = new Criterion\LogicalAnd( $criteria );

        /* Sort Clauses */
        $sortClauses =  array(
            new Content\Query\SortClause\Location\Priority( Content\Query::SORT_ASC )
        );
        if ($customSortClauses) {
            if (is_array($customSortClauses)) {
                $sortClauses = array_merge($customSortClauses, $sortClauses);
            }else {
                array_unshift($sortClauses, $customSortClauses);
            }
        }
        $query->sortClauses = $sortClauses;

        /* Limit and Offset */
        if ( $limit > 0 ) {
            $query->limit = $limit;
        }
        if ( $offset > 0 ) {
            $query->offset = $offset;
        }

        return $this->prepareResults( $this->searchService->findLocations( $query ) );
    }

    public function countChildrenList(Content\Location $parentLocation, $contentTypeIdentifier = null)
    {
        $query = new LocationQuery();

        $criteria = array(
            new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
            new Criterion\ParentLocationId( $parentLocation->id ),
            new Criterion\LanguageCode( $this->configResolver->getParameter( 'languages' )[0] )
        );
        if ( $contentTypeIdentifier )
        {
            $criteria[] = new Criterion\ContentTypeIdentifier( $contentTypeIdentifier );
        }
        $query->limit = 0;

        $query->filter = new Criterion\LogicalAnd( $criteria );

        return $this->searchService->findContent( $query )->totalCount;
    }

    public function findByLocationIds( array $LocationIds )
    {
        $query = new LocationQuery();

        $criteria = array(
            new Criterion\LocationId( $LocationIds ),
            new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
            new Criterion\LanguageCode( $this->configResolver->getParameter( 'languages' )[0] )
        );

        $query->filter = new Criterion\LogicalAnd( $criteria );

        $query->sortClauses = array( new Content\Query\SortClause\Location\Priority( Content\Query::SORT_ASC ) );

        return $this->prepareResults( $this->searchService->findLocations( $query ) );
    }

    /**
     *
     * Liste les enfants triés par priorités définies dans le parent en fonction du groupe de classe
     *
     * @param $parentLocation
     * @param $classGroudId
     * @param null $excludeContentType
     *
     * @return array
     */
    public function findChildrenListByParentAndContentTypeGroupId(Content\Location $parentLocation, $classGroudId, $excludeContentType = null)
    {
        $query = new LocationQuery();

        $criteria[] = new Criterion\LogicalAnd(
            array(
                new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
                new Criterion\ParentLocationId( $parentLocation->id ),
                new Criterion\ContentTypeGroupId( $classGroudId ),
            )
        );
        if ( $excludeContentType )
        {
            $criteria[] = new Criterion\LogicalNot(
                new Criterion\ContentTypeIdentifier( $excludeContentType )
            );
        }

        $query->filter = new Criterion\LogicalAnd( $criteria );

        $query->sortClauses = array( new Content\Query\SortClause\Location\Priority( Content\Query::SORT_ASC ) );

        return $this->prepareResults( $this->searchService->findLocations( $query ) );
    }

    public function findReverseRelationsByContentType( $content, $contentTypeIdentifier = array() )
    {
        $reverseRelations = $this->contentService->loadReverseRelations( $content->contentInfo );
        $relationsIds = array();

        if ( count( $reverseRelations ) )
        {
            foreach ( $reverseRelations as $relation )
            {
                $contentInfo = $this->contentService->loadContentInfo( $relation->sourceContentInfo->id );
                $contentType = $this->contentTypeService->loadContentType( $contentInfo->contentTypeId );
                $identifier = $contentType->identifier;
                if ( empty( $contentType ) || in_array( $identifier, $contentTypeIdentifier ) )
                {
                    $relationsIds[] = $relation->sourceContentInfo->mainLocationId;
                }
            }
            $result = $this->findByLocationIds($relationsIds);
        } else  {
            $result = null;
        }

        return $result;
    }

    public function findRelationsByContentType( $content, $contentTypeIdentifier = array() )
    {
        $relatedObjects = $this->contentService->loadRelations( $content->versionInfo );
        $relations = array();
        if ( count( $relatedObjects ) )
        {
            foreach ( $relatedObjects as $relation )
            {
                $contentInfo = $this->contentService->loadContentInfo( $relation->destinationContentInfo->id );
                $contentType = $this->contentTypeService->loadContentType( $contentInfo->contentTypeId );
                $identifier = $contentType->identifier;
                if ( empty( $contentType ) || in_array( $identifier, $contentTypeIdentifier ) )
                {
                    $relation = $this->locationService->loadLocation( $relation->destinationContentInfo->mainLocationId  );
                    if ( $relation->invisible == false )
                    {
                        $relations[] = $relation;
                    }
                }
            }
        }
        return $relations;
    }

    /**
     * @param Content\Content $content
     * @param $fieldName
     * @return Content\Content[]
     */
    public function findRelationObjectsFromField( Content\Content $content, $fieldName )
    {
        $destinationContentIds = $content->getField( $fieldName )->value->destinationContentIds;
        $relatedObjects = array();
        foreach ( $destinationContentIds as $id )
        {
            $relatedObjects[] = $this->contentService->loadContent( $id );
        }
        return $relatedObjects;
    }

    public function findFirstParentOfType( $currentLocation, $parentContentType )
    {
        $parentLocation = $this->locationService->loadLocation( $currentLocation->id );
        $contentIdentifier = $this->contentTypeService->loadContentType( $parentLocation->contentInfo->contentTypeId )->identifier;

        while ( $contentIdentifier !== $parentContentType && $parentLocation->id != 2 )
        {
            $parentLocation = $this->locationService->loadLocation( $parentLocation->parentLocationId );
            $contentIdentifier = $this->contentTypeService->loadContentType( $parentLocation->contentInfo->contentTypeId )->identifier;
        }

        if ( $parentLocation->id != 2 )
        {
            return $parentLocation;
        }
        return null;
    }

    public function findFirstParent( $currentLocation )
    {
        $parentLocation = $this->locationService->loadLocation( $currentLocation->parentLocationId );

        return $parentLocation;
    }

    public function findNextContent( Content\Location $currentLocation, $contentTypeIdentifier = null )
    {
        $query = new Content\LocationQuery();

        $criteria = array(
            new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
            new Criterion\ParentLocationId( $currentLocation->parentLocationId ),
            new Criterion\LanguageCode( $this->configResolver->getParameter( 'languages' )[0] )
        );
        if ( $contentTypeIdentifier )
        {
            $criteria[] = new Criterion\ContentTypeIdentifier( $contentTypeIdentifier );
        }
        $query->limit = 1;

        $criteria[] = new Criterion\Location\Priority(Operator::GT, $currentLocation->priority );

        $query->filter = new Criterion\LogicalAnd( $criteria );

        $query->sortClauses = array( new Content\Query\SortClause\Location\Priority(Content\Query::SORT_ASC) );

        $result =  $this->prepareResults( $this->searchService->findLocations( $query ) );
        if (count($result) > 0) {
            return $this->smileConvertService->locationToContent(
                $result[0]
            );
        }
        return null;
    }

    public function findPreviousContent( Content\Location $currentLocation, $contentTypeIdentifier = null )
    {
        $query = new Content\LocationQuery();

        $criteria = array(
            new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
            new Criterion\ParentLocationId( $currentLocation->parentLocationId ),
            new Criterion\LanguageCode( $this->configResolver->getParameter( 'languages' )[0] )
        );
        if ( $contentTypeIdentifier )
        {
            $criteria[] = new Criterion\ContentTypeIdentifier( $contentTypeIdentifier );
        }
        $query->limit = 1;

        $criteria[] = new Criterion\Location\Priority(Operator::LT, $currentLocation->priority );

        $query->filter = new Criterion\LogicalAnd( $criteria );

        $query->sortClauses = array( new Content\Query\SortClause\Location\Priority( Content\Query::SORT_DESC ) );

        $result = $this->prepareResults( $this->searchService->findLocations( $query ) );
        if (count($result) > 0) {
            return $this->smileConvertService->locationToContent(
                $result[0]
            );
        }
        return null;
    }

    public function findIndexInParent( Content\Location $location )
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
            new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
            new Criterion\ParentLocationId( $location->parentLocationId ),
            new Criterion\LanguageCode( $this->configResolver->getParameter( 'languages' )[0] )
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
                $criterion = new Criterion\DateMetadata(Criterion\DateMetadata::CREATED, $fiterOperator, $content->versionInfo->creationDate->getTimestamp());
            break;
            case Content\Location::SORT_FIELD_MODIFIED:
                $sortClass = Content\Query\SortClause\DateModified::class;
                $criterion = new Criterion\DateMetadata(Criterion\DateMetadata::MODIFIED, $fiterOperator, $content->versionInfo->modificationDate->getTimestamp());
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
                $criterion = new Criterion\Location\Priority($fiterOperator, $location->priority );
        }

        $criteria[] = $criterion;

        $query->sortClauses = array( new $sortClass($querySortOrder) );
        $query->filter = new Criterion\LogicalAnd($criteria);


        return $this->searchService->findLocations( $query )->totalCount;
    }

    public function findPathArray($location)
    {
        $path_array = explode( "/", $location->pathString );
        $path_array = array_slice( $path_array, 3, count( $path_array ) - 4 );

        return $path_array;
    }

    /**
     * Insère les résultats dans un tableau
     *
     * @param $results
     * @return array
     */
    private function prepareResults($results)
    {
        $res = array();
        foreach ( $results->searchHits as $hit )
        {
            /**
             *
             * @var $hit \eZ\Publish\API\Repository\Values\Content\Search\SearchHit
             */
            $res[] = $hit->valueObject;
        }

        return $res;
    }

    /**
     * @param $url
     * @param null $languageCode
     * @return Content\Location|null
     * @throws NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function findLocationByUrl($url, $languageCode = null)
    {
        try {
            $urlAlias = $this->urlAliasService->lookup($url, $languageCode);
        }catch (NotFoundException $e) {
            return null;
        }
        return $this->locationService->loadLocation($urlAlias->destination);
    }

    public function findFirstValidLocationByUrl($url, $languageCode = null)
    {
        $urlArray = explode('/', $url);
        array_shift($urlArray); // Remove first /
        $foundLocation = null;
        while ($foundLocation == null && count($urlArray) > 0) {
            $foundLocation = $this->findLocationByUrl(implode('/', $urlArray), $languageCode);
            array_pop($urlArray);
        }
        return $foundLocation;
    }
}
