<?php

namespace QUI\FrontendUsers\Controls\Profile;

if (!interface_exists(ControlInterface::class)) {
    interface ControlInterface
    {
        public function onSave();

        public function validate();
    }
}
