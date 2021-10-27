<?php
namespace local_cohortsyncup1\task;

class sync_cohorts_initial extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name()
    {
        return 'Synchroniser les cohortes pour tous les utilisateurs, depuis toujours';
    }

    /**
     * Execute the task.
     */
    public function execute()
    {
        $syncprocess= new \local_cohortsyncup1\synchronize(2);

        $syncprocess->sync_from_users(0, 0);
    }
}