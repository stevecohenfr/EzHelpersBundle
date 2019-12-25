<?php

/**
 * Smile service go get all services in one
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
class SmileServices
{
    protected $smileContentService;

    protected $smileConvertService;

    protected $smileUserService;

    protected $smileFindService;

    /**
     * SmileConvertService constructor.
     *
     * @param SmileContentService $smileContentService
     * @param SmileConvertService $smileConvertService
     * @param SmileUserService    $smileUserService
     * @param SmileFindService    $smileFindService
     *
     * @author Steve Cohen <cohensteve@hotmail.fr>
     */
    public function __construct(SmileContentService $smileContentService, SmileConvertService $smileConvertService,
                                SmileUserService $smileUserService, SmileFindService $smileFindService)
    {
        $this->smileContentService = $smileContentService;
        $this->smileConvertService = $smileConvertService;
        $this->smileUserService = $smileUserService;
        $this->smileFindService =  $smileFindService;
    }

    /**
     * @return SmileContentService
     */
    public function getSmileContentService()
    {
        return $this->smileContentService;
    }

    /**
     * @return SmileConvertService
     */
    public function getSmileConvertService()
    {
        return $this->smileConvertService;
    }

    /**
     * @return SmileUserService
     */
    public function getSmileUserService()
    {
        return $this->smileUserService;
    }

    /**
     * @return SmileFindService
     */
    public function getSmileFindService()
    {
        return $this->smileFindService;
    }


}
