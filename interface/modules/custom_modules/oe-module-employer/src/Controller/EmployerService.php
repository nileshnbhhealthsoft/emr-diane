<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

namespace Juggernaut\OpenEMR\Modules\EmployerModule\Controller;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\Uuid;

class EmployerService
{
    private const MODULE_TABLE = 'employer_data';
    private ?int $id = null;
    private ?int $pid = null;
    private ?string $name = null;
    private ?string $street = null;
    private ?string $street_line_2 = null;
    private ?string $postal_code = null;
    private ?string $city = null;
    private ?string $state = null;
    private ?string $country = null;
    private ?string $start_date = null;
    private ?string $end_date = null;
    private ?string $occupation = null;
    private ?string $industry = null;

    public function storeEmployerInfo(): void
    {
        $uuid = Uuid::create();

        $statement = "INSERT INTO " . self::MODULE_TABLE .
            "(`id`, `uuid`, `pid`, `name`, `street`, `street_line_2`, `postal_code`, `city`, `state`, `country`, " .
            "`start_date`, `end_date`, `occupation`, `industry`, `date`) " .
            "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE " .
            "name = VALUES(name), street = VALUES(street), street_line_2 = VALUES(street_line_2), " .
            "postal_code = VALUES(postal_code), city = VALUES(city), state = VALUES(state), " .
            "country = VALUES(country), start_date = VALUES(start_date), end_date = VALUES(end_date), " .
            "occupation = VALUES(occupation), industry = VALUES(industry), date = NOW()";

        $binding = [];
        $binding[] = $this->id;
        $binding[] = $uuid;
        $binding[] = $this->pid;
        $binding[] = $this->name;
        $binding[] = $this->street;
        $binding[] = $this->street_line_2;
        $binding[] = $this->postal_code;
        $binding[] = $this->city;
        $binding[] = $this->state;
        $binding[] = $this->country;
        $binding[] = $this->start_date;
        $binding[] = $this->end_date;
        $binding[] = $this->occupation;
        $binding[] = $this->industry;
        QueryUtils::sqlInsert($statement, $binding);
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getPid(): ?int
    {
        return $this->pid;
    }

    /**
     * @param int $pid
     */
    public function setPid(int $pid): void
    {
        $this->pid = $pid;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getStreet(): ?string
    {
        return $this->street;
    }

    /**
     * @param string $street
     */
    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    /**
     * @return string|null
     */
    public function getStreetLine2(): ?string
    {
        return $this->street_line_2;
    }

    /**
     * @param string $street_line_2
     */
    public function setStreetLine2(string $street_line_2): void
    {
        $this->street_line_2 = $street_line_2;
    }

    /**
     * @return string|null
     */
    public function getPostalCode(): ?string
    {
        return $this->postal_code;
    }

    /**
     * @param string $postal_code
     */
    public function setPostalCode(string $postal_code): void
    {
        $this->postal_code = $postal_code;
    }

    /**
     * @return string|null
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * @param string $city
     */
    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    /**
     * @return string|null
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * @param string $state
     */
    public function setState(string $state): void
    {
        $this->state = $state;
    }

    /**
     * @return string|null
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    /**
     * @return string|null
     */
    public function getStartDate(): ?string
    {
        return $this->start_date;
    }

    /**
     * @param string $start_date
     */
    public function setStartDate(string $start_date): void
    {
        $this->start_date = $start_date;
    }

    /**
     * @return string|null
     */
    public function getEndDate(): ?string
    {
        return $this->end_date;
    }

    /**
     * @param string $end_date
     */
    public function setEndDate(string $end_date): void
    {
        $this->end_date = $end_date;
    }

    /**
     * @return string|null
     */
    public function getOccupation(): ?string
    {
        return $this->occupation;
    }

    /**
     * @param string $occupation
     */
    public function setOccupation(string $occupation): void
    {
        $this->occupation = $occupation;
    }

    /**
     * @return string|null
     */
    public function getIndustry(): ?string
    {
        return $this->industry;
    }

    /**
     * @param string $industry
     */
    public function setIndustry(string $industry): void
    {
        $this->industry = $industry;
    }

    public function listPatientEmployers(): false|array|\ADORecordSet_mysqli
    {
        $sql = "SELECT DISTINCT pd.pid AS mrn, pd.fname, pd.lname, ed.pid, ed.name, ed.street, ed.city, ed.state, " .
            "ed.start_date, ed.end_date, ed.occupation, ed.industry " .
            "FROM `patient_data` pd " .
            "LEFT JOIN `employer_data` ed ON pd.pid = ed.pid " .
            "ORDER BY pd.lname";

        try {
            $result = sqlStatement($sql);
            return $result;
        } catch (\Throwable $e) {
            error_log("Database error in listPatientEmployers: " . $e->getMessage());
            return false;
        }
    }

    public static function insuranceName($pid): string
    {
        $insurance = sqlQuery("SELECT ic.name FROM `insurance_data` id
            JOIN insurance_companies ic ON id.provider = ic.id
            WHERE `pid` = ? AND type = 'primary'", [$pid]);

        if (is_array($insurance) && array_key_exists('name', $insurance)) {
            return (string) $insurance['name'];
        }

        return '';
    }
}