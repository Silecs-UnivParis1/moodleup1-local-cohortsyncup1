<?php
namespace local_cohortsyncup1\task;

class sync_cohorts extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name()
    {
        return 'Synchroniser les cohortes UP1';
    }

    /**
     * Execute the task.
     */
    public function execute()
    {
        $syncprocess= new \local_cohortsyncup1\synchronize(2);
        $diagcohorts = new \local_cohortsyncup1\diagnostic(1);

        $since = $diagcohorts->get_last_sync('syncAllGroups');
        $syncprocess->sync_all_groups($since['end'], 0);

        $since = $diagcohorts->get_last_sync('syncFromUsers');
        $syncprocess->sync_from_users($since['end'], 0);
    }
}