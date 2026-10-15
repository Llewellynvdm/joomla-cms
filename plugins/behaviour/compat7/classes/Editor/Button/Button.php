<?php

/**
 * Joomla! Content Management System
 *
 * @copyright  (C) 2023 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Editor\Button;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Editor button with legacy property access.
 *
 * @since       __DEPLOY_VERSION__
 * @deprecated  7.0  Will be removed in 8.0. Use the core Button accessors instead.
 */
final class Button implements ButtonInterface
{
    use ButtonTrait;

    /**
     * Magic method to access a property.
     *
     * @param  string  $name  The name of the property.
     *
     * @return mixed A value if the property name is valid, null otherwise.
     *
     * @since       5.0.0
     * @deprecated  7.0  Will be removed in 8.0. Use get() instead.
     */
    public function __get($name)
    {
        @trigger_error('Property access is deprecated in Joomla\CMS\Editor\Button\Button class, use get/set methods. Will be removed in 8.0.', \E_USER_DEPRECATED);

        return $this->get($name);
    }

    /**
     * Magic method to access property.
     *
     * @param  string  $name   The name of the property.
     * @param  mixed   $value  The value of the property.
     *
     * @return void
     *
     * @since       5.0.0
     * @deprecated  7.0  Will be removed in 8.0. Use set() instead.
     */
    public function __set($name, $value)
    {
        @trigger_error('Property access is deprecated in Joomla\CMS\Editor\Button\Button class, use get/set methods. Will be removed in 8.0.', \E_USER_DEPRECATED);

        $this->set($name, $value);
    }
}
