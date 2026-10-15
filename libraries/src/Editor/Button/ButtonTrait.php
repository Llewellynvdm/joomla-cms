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
 * Shared implementation for the core and compatibility editor buttons.
 *
 * @internal
 * @since  __DEPLOY_VERSION__
 */
trait ButtonTrait
{
    /**
     * Button name, CMD string.
     *
     * @return string
     * @since   5.0.0
     */
    protected $name;

    /**
     * Button properties.
     *
     * @return array
     * @since   5.0.0
     */
    protected $props = [];

    /**
     * Button options.
     *
     * @return array
     * @since   5.0.0
     */
    protected $options = [];

    /**
     * Class constructor;
     *
     * @param string $name    The button name
     * @param array  $props   The button properties.
     * @param array  $options The button options.
     *
     * @since   5.0.0
     */
    public function __construct(string $name, array $props = [], array $options = [])
    {
        $this->name    = $name;
        $this->props   = $props;
        $this->options = $options;
    }

    /**
     * Return Button name, CMD string.
     *
     * @return string
     * @since   5.0.0
     */
    public function getButtonName(): string
    {
        return $this->name;
    }

    /**
     * Return Button property.
     *
     * @param string $name    Property name
     * @param string $default Default value
     *
     * @return mixed
     * @since   5.0.0
     */
    public function get(string $name, $default = null)
    {
        if ($name === 'options') {
            @trigger_error(
                'Accessing options property is deprecated. To access the Button options use getOptions() method. Will be removed in 7.0.',
                \E_USER_DEPRECATED
            );

            return $this->getOptions();
        }

        return \array_key_exists($name, $this->props) ? $this->props[$name] : $default;
    }

    /**
     * Set Button property.
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return ButtonInterface
     * @since   5.0.0
     */
    public function set(string $name, $value): ButtonInterface
    {
        if ($name === 'options') {
            @trigger_error(
                'Accessing options property is deprecated. To set the Button options use setOptions() method.  Will be removed in 7.0.',
                \E_USER_DEPRECATED
            );

            return $this->setOptions($value);
        }

        $this->props[$name] = $value;

        return $this;
    }

    /**
     * Return Button options.
     *
     * @return array
     * @since   5.0.0
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set Button options.
     *
     * @param array  $options The button options.
     *
     * @return ButtonInterface
     * @since   5.0.0
     */
    public function setOptions(array $options): ButtonInterface
    {
        $this->options = $options;

        return $this;
    }
}
