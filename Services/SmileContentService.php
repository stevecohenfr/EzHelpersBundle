<?php

/**
 * Smile helper for content manipulation
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

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\User\User;
use eZ\Publish\Core\SignalSlot\Repository;

/**
 * Class SmileContentService
 *
 * @category SmileService
 * @package  Smile\EzHelpersBundle\Services
 * @author   Steve Cohen <cohensteve@hotmail.fr>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     https://github.com/stevecohenfr/EzHelpersBundle Git of EzHelpersBundle
 */
class SmileContentService
{
    protected $repository;

    protected $contentService;

    protected $locationService;

    protected $contentTypeService;

    protected $fieldTypeService;

    protected $searchService;

    protected $smileFindService;

    /**
     * SmileContentService constructor.
     *
     * @param Repository       $repository       eZPlatform API Repository
     * @param SmileFindService $smileFindService Smile Find Service
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function __construct(Repository $repository, SmileFindService $smileFindService)
    {
        $this->repository = $repository;
        $this->contentService = $repository->getContentService();
        $this->locationService = $repository->getLocationService();
        $this->contentTypeService = $repository->getContentTypeService();
        $this->fieldTypeService = $repository->getFieldTypeService();
        $this->searchService = $repository->getSearchService();
        $this->smileFindService = $smileFindService;
    }

    /**
     * Return the content class identifier of a content
     *
     * @param Content $content The content you want the class identifier
     *
     * @return string
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function getClassIdentifier(Content $content)
    {
        $contentType = $this->contentTypeService->loadContentType($content->contentInfo->contentTypeId);
        return $contentType->identifier;
    }

    /**
     * Create and publish a new content in one or more locations
     *
     * @param String $contentTypeidentifier Class identifier that you want to create a content
     * @param array  $parentLocationIds     One or more parent location id where you want to create your object
     * @param array  $cumstomFields         An associative array to fill the field array('field_name' => "Field Value")
     * @param String $lang                  The lang you want to create your content (default: DefaultLanguageCode)
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function createContent($contentTypeidentifier, array $parentLocationIds, $cumstomFields = array(), $lang = null)
    {
        if ($lang == null) {
            $lang = $this->repository->getContentLanguageService()->getDefaultLanguageCode();
        }
        $contentType = $this->contentTypeService->loadContentTypeByIdentifier($contentTypeidentifier);
        $contentCreateStruct = $this->contentService->newContentCreateStruct($contentType, $lang);

        $providedFields = array_keys($cumstomFields);
        $fields = $contentType->getFieldDefinitions();
        foreach ($fields as $field) {
            $fieldIdentifier = $field->identifier;
            if (in_array($fieldIdentifier, $providedFields)) {
                $contentCreateStruct->setField($fieldIdentifier, $cumstomFields[$fieldIdentifier]);
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

    /**
     * Get the path array (array of location ids) of a location
     *
     * @deprecated You can find the path array in location->path
     *
     * @see Location::$path
     *
     * @param  Location $location The location you whant the path array
     *
     * @return array
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function getPathArray(Location $location)
    {
        $path_array = explode("/", $location->pathString);
        $path_array = array_slice($path_array, 3, count($path_array) - 4);

        return $path_array;
    }

    /**
     * Add common relations to the content (without field)
     *
     * @param Content $content   The content
     * @param array   $relations One or more relations to add to the content
     * @param User    $creator   Used as creator of the draft if given - otherwise uses current-user
     *
     * @return Content Return the new version of content with the relations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function addRelationToContent(Content $content, array $relations, User $creator = null)
    {
        $draft = $this->contentService->createContentDraft($content->getVersionInfo()->getContentInfo(), null, $creator);
        if (count($relations) > 0) {
            foreach ($relations as $relation) {
                $this->contentService->addRelation($draft->getVersionInfo(), $relation->getVersionInfo()->getContentInfo());
            }
        }
        return $this->contentService->publishVersion($draft->getVersionInfo());
    }

    /**
     * Extract selection values labels
     *
     * @param Content     $content
     * @param string      $fieldIdentifier
     * @param string|null $lang
     *
     * @return array
     * @throws NotFoundException
     */
    public function getSelectionValue(Content $content, string $fieldIdentifier, string $lang = null) {
        $contentType = $this->contentTypeService->loadContentType($content->contentInfo->contentTypeId);

        $values = array();
        $options = $contentType->getFieldDefinition($fieldIdentifier)->getFieldSettings()['options'];
        foreach($content->getFieldValue($fieldIdentifier, $lang)->selection as $selection) {
            $values[] = $options[$selection];
        }
        return $values;
    }

    /**
     * Get an array of all fields indexed by fieldDefIdentifier
     *
     * @param Content|User $content       The content to extract the fields
     * @param boolean      $useFieldName  Use the field name instead of fieldDefIdentifier
     * @param array        $ignoredFields An array of fieldDefIdentifier you don't want to extract
     * @param string       $lang          The lang you want to create your content (default: DefaultLanguageCode)
     * @param int          $depth         The depth to load sub contents in ezobjectrelation and ezobjectrelationlist
     *
     * @return array
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function extractContentFields($content, $useFieldName = false, array $ignoredFields = array(), string $lang = null, $depth = 0)
    {
        if ($lang == null) {
            $lang = $this->repository->getContentLanguageService()->getDefaultLanguageCode();
        }
        $contentType = $this->contentTypeService->loadContentType($content->contentInfo->contentTypeId);
        $fieldsArray = array();
        $fields = $content->getFields();
        foreach ($fields as $field) {
            $value = $content->getFieldValue($field->fieldDefIdentifier, $lang);
            if ($value != null) {
                switch($field->fieldTypeIdentifier) {
                    case 'ezselection':
                        $value = implode(', ', $this->getSelectionValue($content, $field->fieldDefIdentifier, $lang));
                        break;
                    case 'ezobjectrelation':
                        $relation = $this->smileFindService->findRelationObjectFromField($content, $field->fieldDefIdentifier);
                        if ($depth > 0) {
                            $value = $this->extractContentFields($relation, $useFieldName, $ignoredFields, $lang, $depth-1);
                        }else {
                            $value = $relation->getName($lang);
                        }
                        break;
                    case 'ezobjectrelationlist':
                        $relations = $this->smileFindService->findRelationObjectsFromField($content, $field->fieldDefIdentifier);
                        $value = array();
                        /** @var Content $relation */
                        foreach ($relations as $relation) {
                            if ($depth > 0) {
                                $value[] = $this->extractContentFields($relation, $useFieldName, $ignoredFields, $lang, $depth-1);
                            }else {
                                $value[] = $relation->getName($lang);
                            }
                        }
                        break;
                    case 'ezboolean':
                        $value = $value->__toString() === "1" ? "✔" : "✕";
                        break;
                    case 'ezdate':
                        $value = $value->date ? $value->date->format("d/m/Y") : null;
                        break;
                    case 'ezdatetime':
                        $value = $value->date ? $value->date->format("d/m/Y H:i:s") : null;
                        break;
                    default:
                        $value = $value->__toString();
                }
            }

            if (!in_array($field->fieldDefIdentifier, $ignoredFields)) {
                $fieldsArray[
                $useFieldName ?
                    $contentType->getFieldDefinition($field->fieldDefIdentifier)->getName($lang) :
                    $field->fieldDefIdentifier
                ] = $value;
            }
        }
        if ($content instanceof User) {
            if (!in_array('account[login]', $ignoredFields)) {
                $fieldsArray[$useFieldName ? 'Login' : 'account[login]'] = $content->login;
            }
            if (!in_array('account[email]', $ignoredFields)) {
                $fieldsArray[$useFieldName ? 'Email' : 'account[email]'] = $content->email;
            }
        }

        return $fieldsArray;
    }

    /**
     *
     * Change the publish date of a content
     *
     * @param Content   $content The content you want change the publish date
     * @param \DateTime $dateTime New date for the content publication
     * @param boolean   $updateModificationDate If you want to also update the modificationDate
     *
     * @return Content The updated content
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function updatePublishDate(Content $content, \DateTime $dateTime, $updateModificationDate = false) {
        $oldMetadata = $content->versionInfo->contentInfo;
        $newContentMetadataUpdateStruct = $this->contentService->newContentMetadataUpdateStruct();
        $newContentMetadataUpdateStruct->ownerId = $oldMetadata->ownerId;
        $newContentMetadataUpdateStruct->mainLanguageCode = $oldMetadata->mainLanguageCode;
        $newContentMetadataUpdateStruct->alwaysAvailable = $oldMetadata->alwaysAvailable;
        $newContentMetadataUpdateStruct->remoteId = $oldMetadata->remoteId;
        $newContentMetadataUpdateStruct->mainLocationId = $oldMetadata->mainLocationId;

        $newContentMetadataUpdateStruct->publishedDate = $dateTime;
        $content->contentInfo->publishedDate->setTimestamp($dateTime->getTimestamp());
        if ($updateModificationDate) {
            $newContentMetadataUpdateStruct->modificationDate = $dateTime;
            $content->contentInfo->modificationDate->setTimestamp($dateTime->getTimestamp());
        }
        return $this->contentService->updateContentMetadata($content->contentInfo, $newContentMetadataUpdateStruct);
    }

    /**
     *
     * Update content with given data in associative array (keys are fieldIdentifiers, values fields values)
     *
     * @param Content $content The content you want to update
     * @param array   $data An associative array (keys are fieldIdentifiers, values fields values)
     *
     * @return Content The new updated content
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    function updateContentFields(Content $content, array $data = array()) {
        $contentType = $content->getContentType();
        $contentDraft = $this->contentService->createContentDraft($content->contentInfo);
        $contentUpdateStruct = $this->contentService->newContentUpdateStruct();

        $providedFields = array_keys($data);
        $fields = $contentType->getFieldDefinitions();
        foreach ($fields as $field) {
            $fieldIdentifier = $field->identifier;
            if (in_array($fieldIdentifier, $providedFields)) {
                $contentUpdateStruct->setField($fieldIdentifier, $data[$fieldIdentifier]);
            }
        }

        $contentDraft = $this->contentService->updateContent($contentDraft->versionInfo, $contentUpdateStruct);

        $content = $this->contentService->publishVersion($contentDraft->versionInfo);

        return $content;
    }

    /**
     * Replace content owner by new owner
     *
     * @param Content $content The content to update
     * @param User    $owner The new owner
     *
     * @return Content
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    function updateContentOwner(Content $content, User $owner) {
        $contentDraft = $this->contentService->createContentDraft($content->contentInfo);
        $contentUpdateStruct = $this->contentService->newContentUpdateStruct();

        $contentUpdateStruct->creatorId = $owner->id;

        $contentDraft = $this->contentService->updateContent($contentDraft->versionInfo, $contentUpdateStruct);

        $content = $this->contentService->publishVersion($contentDraft->versionInfo);

        return $content;
    }

    /**
     * Load multiple content with given array of ids
     *
     * @param array $contentIds
     *
     * @return Content[]
     *
     * @throws NotFoundException
     */
    function loadContents(array $contentIds) {
        $contents = array();
        foreach ($contentIds as $contentId) {
            $contents[] = $this->contentService->loadContent($contentId);
        }
        return $contents;
    }
}
