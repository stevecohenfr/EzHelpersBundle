<?php
/**
 * Created by PhpStorm.
 * User: stcoh
 * Date: 03/11/17
 * Time: 11:47
 */

namespace Smile\EzHelpersBundle\Services;

use eZ\Publish\API\Repository\Values\User\User;
use eZ\Publish\API\Repository\Values\User\UserGroup;
use eZ\Publish\Core\SignalSlot\Repository;

class SmileUserService
{
    const ADMINISTRATOR_USER_ID = 14;

    private $lastUser;

    protected $repository;

    protected $contentService;

    protected $locationService;

    protected $contentTypeService;

    protected $fieldTypeService;

    protected $searchService;

    protected $userService;

    protected $permissionResolver;

    public function  __construct(Repository $repository)
    {
        $this->repository = $repository;
        $this->contentService = $repository->getContentService();
        $this->locationService = $repository->getLocationService();
        $this->contentTypeService = $repository->getContentTypeService();
        $this->fieldTypeService = $repository->getFieldTypeService();
        $this->searchService = $repository->getSearchService();
        $this->userService = $repository->getUserService();
        $this->permissionResolver = $repository->getPermissionResolver();
        $this->lastUser = $this->permissionResolver->getCurrentUserReference();
    }

    /**************************************************************
     * ************************** Users ***************************
     **************************************************************/

    /**
     * @return \eZ\Publish\API\Repository\Values\User\UserReference
     */
    public function getCurrentUser()
    {
        return $this->permissionResolver->getCurrentUserReference();
    }

    /**
     * @return \eZ\Publish\API\Repository\Values\User\UserReference
     */
    public function saveCurrentUser()
    {
        $this->lastUser = $this->getCurrentUser();

        return $this->lastUser;
    }

    /**
     * @return \eZ\Publish\API\Repository\Values\User\UserReference
     */
    public function restoreLastUser()
    {
        $this->permissionResolver->setCurrentUserReference($this->lastUser);

        return $this->lastUser;
    }

    /**
     * @param User $user
     * @return User
     */
    public function login(User $user)
    {
        $this->saveCurrentUser();
        $this->permissionResolver->setCurrentUserReference($user);

        return $user;
    }

    /**
     * @param String $login
     * @return User
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function loginByLogin(String $login)
    {
        $user = $this->userService->loadUserByLogin($login);

        return $this->login($user);
    }

    /**
     * @param Int $id
     * @return User
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function loginById(Int $id)
    {
        $user = $this->userService->loadUser($id);

        return $this->login($user);
    }

    /**
     * Login with the default administrator id (14)
     *
     * @return User
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function loginAsAdministrator()
    {
        return $this->loginById(self::ADMINISTRATOR_USER_ID);
    }

    /**
     * @param String $login
     * @param String $password
     * @param String $email
     * @param array $groupIds
     * @param String|null $lang
     * @return User
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function createUser(String $login, String $password, String $email, array $groupIds, String $lang = null)
    {
        if ($lang == null) {
            $lang = $this->repository->getContentLanguageService()->getDefaultLanguageCode();
        }
        $groups = array();
        foreach ($groupIds as $groupId) {
            $groups[] = $this->getUserGroupById($groupId);
        }
        $userCreateStruct = $this->userService->newUserCreateStruct($login, $email, $password, $lang);
        $user = $this->userService->createUser($userCreateStruct, $groups);

        return $user;
    }

    /**************************************************************
     * *********************** User Groups ************************
     **************************************************************/

    /**
     * @param Int $id
     * @return \eZ\Publish\API\Repository\Values\User\UserGroup
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function getUserGroupById(Int $id)
    {
        return $this->userService->loadUserGroup($id);
    }

    /**
     * @param Int $parentId
     * @param String|null $lang
     * @return \eZ\Publish\API\Repository\Values\User\UserGroup
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function createUserGroup(Int $parentId, String $lang = null)
    {
        if ($lang == null) {
            $lang = $this->repository->getContentLanguageService()->getDefaultLanguageCode();
        }
        $userGroupCreateStruct = $this->userService->newUserGroupCreateStruct($lang);
        $userGroup = $this->userService->createUserGroup($userGroupCreateStruct, $this->getUserGroupById($parentId));

        return $userGroup;
    }

    /**
     * @param User $user
     * @param UserGroup $group
     * @param bool $removeFromOtherGroups
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function assignUserToGroup(User $user, UserGroup $group, bool $removeFromOtherGroups = false)
    {
        if ($removeFromOtherGroups == true) {
            $userGroups = $this->userService->loadUserGroupsOfUser($user, 0, 1000);
            foreach ($userGroups as $userGroup) {
                $this->unAssignUserFromUserGroup($user, $userGroup);
            }
        }
        $this->userService->assignUserToUserGroup($user, $group);
    }

    /**
     * @param $user
     * @param $group
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function unAssignUserFromUserGroup($user, $group)
    {
        $this->userService->unAssignUserFromUserGroup($user, $group);
    }
}
