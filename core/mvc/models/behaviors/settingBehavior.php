<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Security\SettingsCipher;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * Write rules for application settings.
 *
 * - Secure values are encrypted before validation, so every later step
 *   (including auditBehavior) only ever sees ciphertext.
 * - An empty value on a secure setting keeps the stored one.
 * - name, groupName and secure are fixed once the row exists.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\Models\Behaviors
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class settingBehavior extends Behavior implements BehaviorInterface
{
    /**
     * Fields that cannot change after the setting is created.
     *
     * @var array
     */
    public const FIXED_FIELDS = ['name', 'groupName', 'secure'];

    /**
     * Cipher used for secure values; resolved from config when not injected.
     *
     * @var SettingsCipher|null
     */
    private $cipher;

    /**
     * @param SettingsCipher|null $cipher Injected in tests; defaults to the configured key.
     */
    public function __construct(SettingsCipher $cipher = null)
    {
        $this->cipher = $cipher;
    }

    /**
     * Returning false from a before* event cancels the save.
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            return $this->$eventType($model);
        }
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnCreate($model)
    {
        if (!$this->isSecure($model) || $this->isEmpty($model->value)) {
            return true;
        }

        return $this->encryptValue($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $original = $model->getSnapshotData();

        foreach (self::FIXED_FIELDS as $field) {
            if (isset($original[$field]) && (string) $model->$field !== (string) $original[$field]) {
                return $this->reject($model, "The setting {$field} cannot be changed.");
            }
        }

        if (!$this->isSecure($model)) {
            return true;
        }

        $storedValue = isset($original['value']) ? $original['value'] : null;

        // Blank means "keep the current secret".
        if ($this->isEmpty($model->value)) {
            $model->value = $storedValue;
            return true;
        }

        // Unchanged ciphertext is fine; any other pre-encrypted value is not accepted from clients.
        if (SettingsCipher::isEncrypted($model->value)) {
            return $model->value === $storedValue
                ? true
                : $this->reject($model, 'Secure settings must be sent as plain text.');
        }

        return $this->encryptValue($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    private function encryptValue($model)
    {
        $cipher = $this->getCipher();
        if (!$cipher->hasKey()) {
            return $this->reject($model, 'Secure settings cannot be saved: SETTINGS_ENCRYPTION_KEY is not configured.');
        }

        $model->value = $cipher->encrypt($model->value);
        return true;
    }

    /**
     * @return SettingsCipher
     */
    private function getCipher()
    {
        if ($this->cipher === null) {
            $this->cipher = SettingsCipher::fromConfig();
        }

        return $this->cipher;
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    private function isSecure($model)
    {
        return (int) $model->secure === 1;
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    private function isEmpty($value)
    {
        return $value === null || $value === '';
    }

    /**
     * Add a validation message and cancel the save.
     *
     * @param  ModelInterface $model
     * @param  string         $message
     * @return bool
     */
    private function reject($model, $message)
    {
        $model->appendMessage(new Message($message, 'value', 'InvalidValue'));
        return false;
    }
}
