<?php
namespace local_cohortsyncup1\task;

use \local_cohortsyncup1\synchronize;
use \local_cohortsyncup1\diagnostic;

class synchronize_cohorts extends \core\task\scheduled_task {

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
        $cohortsyncprocess= new synchronize(2);
        $diagcohorts = new diagnostic(1);

        $since = $diagcohorts->get_last_sync('syncAllGroups');
        $sync->sync_all_groups($since, 0);

        $since = $diagcohorts->get_last_sync('syncFromUsers');
        $sync->sync_from_users($since, 0);
    }
}