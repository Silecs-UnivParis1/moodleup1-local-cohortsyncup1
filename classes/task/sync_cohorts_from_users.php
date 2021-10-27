<?php
namespace local_cohortsyncup1\task;

class sync_cohorts_from_users extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name()
    {
        return 'Synchroniser les cohortes UP1 à partir des utilisateurs seulement';
    }

    /**
     * Execute the task.
     */
    public function execute()
    {
        $syncprocess= new \local_cohortsyncup1\synchronize(2);
        $diagcohorts = new \local_cohortsyncup1\diagnostic(1);

        $since = $diagcohorts->get_last_sync('syncFromUsers');
        $syncprocess->sync_from_users($since['end'], 0);
    }
}