<?php

namespace App\Grid;

use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Ublaboo\DataGrid\Column\ColumnText;
use Ublaboo\DataGrid\DataGrid;

abstract class Grid
{
    protected $database;
    protected $grid;

    /**
     * Grid constructor.
     * @param Context $database
     */
    public function __construct(Context $database)
    {
        $this->database = $database;
    }

    protected function createDatagrid()
    {
        $grid = new MyDataGrid();
        $grid->setTemplateFile(__DIR__ . '/templates/datagrid.latte');
        return $grid;
    }
}

class MyDataGrid extends DataGrid
{
    function showProfileColumnWithEmail($relatedTableName = 'data_user')
    {
        return $this
            ->createProfileColumnBase($relatedTableName)
            ->setTemplate(__DIR__ . '/templates/grid.profile.latte');
    }

    function showProfileColumnWithoutEmail()
    {
        return $this
            ->createProfileColumnBase()
            ->setTemplate(__DIR__ . '/templates/grid.esnmembers.latte');
    }

    function showCountry()
    {
        $this->addColumnText('country', 'Country', "data_user.country_id")
            ->setTemplate(__DIR__ . '/templates/grid.flag.latte')
            ->setSortable("data_user.country_id.name")
            ->setFilterText('data_user.country_id.name');
    }

    function showRegisteredDate()
    {
        $this->addColumnDateTime('registered', 'Registered', "data_user.registered")
            ->setSortable()
            ->setFilterDateRange();
    }

    function showHomeUniversity()
    {
        $this->addColumnText('university', 'Home University')
            ->setRenderer(function ($item) {
                return $item->ref("user", "data_user")->ref("university", "university")->name;
            })
            ->setSortable("user.user_id.university")
            ->setFilterText('user.user_id.university.name');
    }

    function showFaculty($faculties)
    {
        $faculties["multiselect"] = $faculties["short"];
        $faculties["short"][""] = "Unknown";

        $this->addColumnText('faculty', 'Faculty', "data_user.faculty_id")
            ->setSortable("data_user.faculty_id")
            ->setReplacement($faculties["short"])
            ->setFilterMultiSelect($faculties["multiselect"]);
    }

    function showExportCsv()
    {
        $name = new ColumnText($this, 'name', 'data_user.name', 'Name');
        $surname = (new ColumnText($this, 'surname', 'data_user.surname', 'Surname'));
        $birthday = (new ColumnText($this, 'birthday', 'data_user.birthday', 'Birthday'));
        $country = (new ColumnText($this, 'country', 'data_user.country_id', 'Country'));
        $email = (new ColumnText($this, 'email', 'user.user_id', 'Email'));
        $registered = (new ColumnText($this, 'registered', 'data_user.registered', 'Registered'));

        $birthday->setRenderer(function (ActiveRow $row) {
            /** @var DateTime|NULL $value */
            $value = $row->ref('data_user')->birthday;
            if (!$value) return '';

            $date = $value->format('Y-m-d');
            return $date != '1970-01-01' ? $date : NULL;
        });

        $this->addExportCsvFiltered('Csv export', "fiesta.csv", "UTF-8", ";", true)
            ->setTitle('Csv export')
            ->setColumns([
                $name,
                $surname,
                $birthday,
                $email,
                $country,
                $registered
            ]);
    }

    public function showEditableStatus(callable $onSuccess)
    {

        $this->addColumnStatus('status', 'Status', "user.status:data_user")
            ->setSortable("user.user_id.status")
            ->addOption("active", 'Active')
            ->setClass('btn-success')
            ->endOption()
            ->addOption("pending", 'Pending')
            ->setClass('btn-warning')
            ->endOption()
            ->addOption("enabled", 'Disabled')
            ->setClass('btn-secondary')
            ->endOption()
            ->addOption("banned", 'Banned')
            ->setClass('btn-danger')
            ->endOption()
            ->onChange[] = function ($id, $new_value) use ($onSuccess) {
            $onSuccess($id, $new_value);
            die;
        };

        $this->addFilterMultiSelect('status', 'Status:', ([
            "active" => 'Active',
            "pending" => 'Pending',
            "banned" => 'Banned',
            "enabled" => 'Disabled'
        ]), "user.user_id.status");
    }

    function showDeleteRequest(callable $onSuccess)
    {
        $this->addActionCallback("delete", "", function ($id) use ($onSuccess) {
            $onSuccess($id);
            die;
        })
            ->setIcon('trash')
            ->setTitle('Delete')
            ->setClass('btn btn-xs btn-danger')
            ->setConfirm('Do you really want to delete this request?');
    }

    function showESNCard()
    {
        $this->addColumnText('esn', 'ESNCard', "data_user.esn_card")
            ->setSortable("data_user.esn_card")
            ->setReplacement(['' => 'Unknown'])
            ->setFilterText('data_user.esn_card');
    }

    function showPhoneNumber()
    {
        $this->addColumnText('phone', 'Phone', "data_user.phone_number")
            ->setSortable("data_user.phone_number")
            ->setFilterText('data_user.phone_number');
    }

    function showSimplifiedStatus()
    {
        $this->addColumnText('status', 'Status', "user.status:data_user")
            ->setSortable("user.user_id.status")
            ->setReplacement([
                "active" => "Active",
                "enabled" => 'Alumni']);

        $this->addFilterMultiSelect('status', 'Status:', ([
            "active" => 'Active',
            "enabled" => 'Alumni'
        ]), "user.user_id.status");
    }

    /**
     * @param string $relatedTableName customized table name for filter when `data_user` IS NOT base table of datasource
     * @return ColumnText
     */
    protected function createProfileColumnBase($relatedTableName = 'data_user')
    {
        $col = $this
            ->addColumnText('name', 'Name')
            ->setSortable("$relatedTableName.name");

        $col
            ->setFilterText()
            ->setCondition(function ($fluent, $value) use ($relatedTableName) {
                $fluent->where(
                    "concat_ws(' ', $relatedTableName.name, $relatedTableName.surname) LIKE ? OR $relatedTableName.user_id LIKE ?",
                    ["%$value%", "%$value%"]
                );
            });
        return $col;
    }
}