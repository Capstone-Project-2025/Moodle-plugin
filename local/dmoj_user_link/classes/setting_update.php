<?php

class admin_setting_configtext_notify extends admin_setting_configtext {
    public function write_setting($data) {
        $oldvalue = $this->get_setting();

        // before updating the setting, trigger an event if the value is changing
        if ($oldvalue !== $data) {
            \local_dmoj_user_link\event\before_setting_updated::create([
                'context' => \context_system::instance(),
                'other' => [
                    'name' => $this->name,
                    'oldvalue' => $oldvalue,
                    'newvalue' => $data,
                ],
            ])->trigger();
        }

        $status = parent::write_setting($data);
        
        // if the setting was successfully updated and the value has changed, trigger the after event
        if ($status === '' && $oldvalue !== $data) {
            \local_dmoj_user_link\event\after_setting_updated::create([
                'context' => \context_system::instance(),
                'other' => [
                    'name' => $this->name,
                    'oldvalue' => $oldvalue,
                    'newvalue' => $data,
                ],
            ])->trigger();
        }
        return $status;
    }
}
