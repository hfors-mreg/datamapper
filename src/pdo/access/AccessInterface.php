<?php
/**
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://www.wtfpl.net/ for more details.
 */

namespace datamapper\pdo\access;

/**
 * Access control flags and names of access fields
 *
 * @author Hannes Forsgård <hannes.forsgard@fripost.org>
 */
interface AccessInterface
{
    /**
     * Name of owner field in db
     */
    const OWNER_FIELD = 'owner';

    /**
     * Name of group field in db
     */
    const GROUP_FIELD = 'group';

    /**
     * Name of mode field in db
     */
    const MODE_FIELD = 'mode';

    /**
     * Owner read, write and execute permission flag
     */
    const OWNER_ALL = 0700;

    /**
     * Owner read permission flag
     */
    const OWNER_READ = 0400;

    /**
     * Owner write permission flag
     */
    const OWNER_WRITE = 0200;

    /**
     * Owner execute permission flag
     */
    const OWNER_EXECUTE = 0100;

    /**
     * Group read, write and execute permission flag
     */
    const GROUP_ALL = 070;

    /**
     * Group read permission flag
     */
    const GROUP_READ = 040;

    /**
     * Group write permission flag
     */
    const GROUP_WRITE = 020;

    /**
     * Group execute permission flag
     */
    const GROUP_EXECUTE = 010;

    /**
     * Others read, write and execute permission flag
     */
    const OTHERS_ALL = 07;

    /**
     * Others read permission flag
     */
    const OTHERS_READ = 04;

    /**
     * Others write permission flag
     */
    const OTHERS_WRITE = 02;

    /**
     * Others execute permission flag
     */
    const OTHERS_EXECUTE = 01;
}
