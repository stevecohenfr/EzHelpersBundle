<?php

/**
 * Smile helper to manipulate users
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

use eZ\Publish\API\Repository\Values\User\User;
use eZ\Publish\API\Repository\Values\User\UserGroup;
use eZ\Publish\Core\SignalSlot\Repository;

/**
 * Class SmileUserService
 *
 * @category SmileService
 * @package  Smile\EzHelpersBundle\Services
 * @author   Steve Cohen <cohensteve@hotmail.fr>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     https://github.com/stevecohenfr/EzHelpersBundle Git of EzHelpersBundle
 */
class SmileUserService
{
    const ADMINISTRATOR_USER_ID = 14;

    private $_lastUser;

    protected $repository;

    protected $contentService;

    protected $locationService;

    protected $contentTypeService;

    protected $fieldTypeService;

    protected $searchService;

    protected $userService;

    protected $permissionResolver;

    /**
     * SmileUserService constructor.
     *
     * @param Repository $repository eZPlatform API Repository
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
        $this->contentService = $repository->getContentService();
        $this->locationService = $repository->getLocationService();
        $this->contentTypeService = $repository->getContentTypeService();
        $this->fieldTypeService = $repository->getFieldTypeService();
        $this->searchService = $repository->getSearchService();
        $this->userService = $repository->getUserService();
        $this->permissionResolver = $repository->getPermissionResolver();
        $this->_lastUser = $this->permissionResolver->getCurrentUserReference();
    }

    /**************************************************************
     * ************************** Users ***************************
     **************************************************************/

    /**
     * Get the current user
     *
     * @return \eZ\Publish\API\Repository\Values\User\UserReference
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function getCurrentUser()
    {
        return $this->permissionResolver->getCurrentUserReference();
    }

    /**
     * Save the current user in a variable to be restored later
     *
     * @return \eZ\Publish\API\Repository\Values\User\UserReference
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function saveCurrentUser()
    {
        $this->_lastUser = $this->getCurrentUser();

        return $this->_lastUser;
    }

    /**
     * Restore the last saved current user. If no current user is saved, the current user will stay
     *
     * @return \eZ\Publish\API\Repository\Values\User\UserReference
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function restoreLastUser()
    {
        $this->permissionResolver->setCurrentUserReference($this->_lastUser);

        return $this->_lastUser;
    }

    /**
     * Login as a user
     *
     * @param User $user The user you want to login
     *
     * @return User
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function login(User $user)
    {
        $this->saveCurrentUser();
        $this->permissionResolver->setCurrentUserReference($user);

        return $user;
    }

    /**
     * Login as a user using his login
     *
     * @param String $login The user login
     *
     * @return User
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function loginByLogin(String $login)
    {
        $user = $this->userService->loadUserByLogin($login);

        return $this->login($user);
    }

    /**
     * Login as a user using his id
     *
     * @param Int $id The user id
     *
     * @return User
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
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
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function loginAsAdministrator()
    {
        return $this->loginById(self::ADMINISTRATOR_USER_ID);
    }

    /**
     * Create and publish a new user
     *
     * @param String $login    The login of the new user
     * @param String $password The password of the new user
     * @param String $email    The email of the new user
     * @param array  $groupIds One or more groups to assign the new user
     * @param String $lang     The lang you want to create your content (default: DefaultLanguageCode)
     *
     * @return User
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
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
     * Get the user group by id
     *
     * @param Int $id The UserGroup id
     *
     * @return \eZ\Publish\API\Repository\Values\User\UserGroup
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function getUserGroupById(Int $id)
    {
        return $this->userService->loadUserGroup($id);
    }

    /**
     * Create and publish a new UserGroup
     *
     * @param Int    $parentId The location id where to create the new UserGroup
     * @param String $lang     The lang you want to create your content (default: DefaultLanguageCode)
     *
     * @return \eZ\Publish\API\Repository\Values\User\UserGroup
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
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
     * Assign a user to a group
     *
     * @param User      $user                  The user you want to assign
     * @param UserGroup $group                 The group you want to assign the user
     * @param bool      $removeFromOtherGroups If true, the user will be unassigned from all of his other groups
     *
     * @return void
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
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
     * Unassign a user from a UserGroup
     *
     * @param User      $user  The user you want to unassign
     * @param UserGroup $group The group you want the user to be unassigned
     *
     * @return void
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function unAssignUserFromUserGroup(User $user, UserGroup $group)
    {
        $this->userService->unAssignUserFromUserGroup($user, $group);
    }
}
