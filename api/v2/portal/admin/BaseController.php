<?php

declare(strict_types=1);

namespace Portal\Admin;

/**
 * Base controller for all DS3 admin portal endpoints.
 */
abstract class BaseController extends \Portal\BaseController
{
    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
}
