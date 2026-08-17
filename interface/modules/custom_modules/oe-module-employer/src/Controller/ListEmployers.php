<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

namespace Juggernaut\OpenEMR\Modules\EmployerModule\Controller;

class ListEmployers
{
    private int $pid;

    /**
     * @param int $pid
     */
    public function setPid(int $pid): void
    {
        $this->pid = $pid;
    }

    public function getAllEmployers(): false|array|\ADORecordSet_mysqli
    {
        $sql = "SELECT *
                    FROM employer_data
                    WHERE pid = ? ORDER BY `start_date` DESC";
        return sqlStatement($sql, [$this->pid]);
    }

    /**
     * @return void
     * this method is to back populate the module table in case
     * the employer data was entered through the demographics form.
     * this is a silent function
     */
    public static function insertMissingEmployersFromForm(): void
    {
        $session = \OpenEMR\Common\Session\SessionWrapperFactory::getInstance()->getActiveSession();
        $sql = "SELECT id FROM employer_data WHERE pid = ? ORDER BY id ASC LIMIT 1";
        $existing = sqlQuery($sql, [$session->get('pid')]);
        // If there is no employer data for the patient yet, nothing to do.
        if (empty($existing)) {
            return;
        }
    }

    public function findPatientsWithEmployers(): array
    {
        $list = [];
        $sql = "SELECT DISTINCT `pid` FROM `employer_data` ORDER BY `pid` ASC";
        $load = sqlStatement($sql);
        while ($row = sqlFetchArray($load)) {
            $list[] = $row['pid'];
        }
        return $list;
    }
}