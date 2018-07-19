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

use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\User\Limitation\SubtreeLimitation;
use eZ\Publish\API\Repository\Values\User\Role;
use eZ\Publish\API\Repository\Values\User\User;
use eZ\Publish\API\Repository\Values\User\UserGroup;
use eZ\Publish\Core\SignalSlot\Repository;
use eZ\Publish\API\Repository\Values\User\Limitation\RoleLimitation;

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

    protected $roleService;

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
        $this->roleService = $repository->getRoleService();
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
        $this->repository->setCurrentUser($user);

        return $user;
    }

    /**
     * Login as a user using his login
     *
     * @param String $username The user login
     *
     * @return User
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function loginByUsername(String $username)
    {
        $user = $this->userService->loadUserByLogin($username);

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

    /**
     * Convert a content to user (works if the content IS a user)
     * You can use it to get a user from object relation(s)
     *
     * @param Content $content The content that is a user
     *
     * @return User
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function contentToUser(Content $content)
    {
        $user = $this->userService->loadUser($content->id);

        return $user;
    }

    /**************************************************************
     * *********************** User Groups ************************
     **************************************************************/

    /**
     * Get the user group by id
     *
     * @param Int $id The UserGroup id (content id)
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
     * @param Int    $parentGroupId The content id of the parent UserGroup
     * @param String $lang          The lang you want to create your content (default: DefaultLanguageCode)
     * @param String $name          The UserGroup name
     * @param String $description   The optional UserGroup description
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
    public function createUserGroup(Int $parentGroupId, String $name, String $description = "", String $lang = null)
    {
        if ($lang == null) {
            $lang = $this->repository->getContentLanguageService()->getDefaultLanguageCode();
        }
        $userGroupCreateStruct = $this->userService->newUserGroupCreateStruct($lang);
        $userGroupCreateStruct->setField('name', $name);
        $userGroupCreateStruct->setField('description', $description);
        $userGroup = $this->userService->createUserGroup($userGroupCreateStruct, $this->getUserGroupById($parentGroupId));

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
            $this->unAssignUserFromAllUserGroups($user);
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

    /**
     * Unassign the user from all his UserGroups
     *
     * @param User $user The user you want to unassign
     *
     * @return int The number of groups user has been unassigned
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function unAssignUserFromAllUserGroups(User $user)
    {
        $userGroups = $this->userService->loadUserGroupsOfUser($user, 0, 1000);
        foreach ($userGroups as $userGroup) {
            $this->unAssignUserFromUserGroup($user, $userGroup);
        }

        return count($userGroups);
    }

    /**
     * Unassign all users from the given group
     *
     * @param UserGroup $group The group you want to unassign all users
     *
     * @return int The number of users unassigned
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function unAssignAllUsersFromUserGroup(UserGroup $group)
    {
        $users = $this->userService->loadUsersOfUserGroup($group);
        foreach ($users as $user) {
            $this->unAssignUserFromUserGroup($user, $group);
        }

        return count($users);
    }

    /**
     * Assign a Role to a UserGroup with a limitation on a subtree
     *
     * @param Role      $role The role to assign
     * @param UserGroup $group The group to assign the role
     * @param Location  $treeRoot The subtree location root for the limitation
     *
     * @return void
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\LimitationValidationException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function assignRoleToUserGroupLimitedBySubtree(Role $role, UserGroup $group, Location $treeRoot)
    {
        $limitations = array();
        $subtreeLimitation = new SubtreeLimitation();
        $subtreeLimitation->limitationValues[] = $treeRoot->pathString;
        $limitations[] = $subtreeLimitation;
        $this->roleService->assignRoleToUserGroup($role, $group, $subtreeLimitation);
    }

    /**
     * Get a role by id
     *
     * @param int $id The role id
     *
     * @return Role
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function getRoleById(int $id)
    {
        $role = $this->roleService->loadRole($id);

        return $role;
    }
}
