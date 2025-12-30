<?php

namespace ovidiuro\myfinance2\App\Services;

abstract class MyFormFields
{
    /**
     * MODIFIED: Declared $id property to prevent dynamic property deprecation warning
     * In PHP 8.2+, creating undeclared dynamic properties is deprecated
     * @var int|null
     */
    protected $id = null;

    abstract protected function model();

    /**
     * Create a new job instance.
     *
     * @param int $id
     *
     * @return void
     */
    public function __construct($id = null)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @param int $parentId
     *
     * @return array
     */
    public function handle($parentId = null)
    {
        $fields = $this->fieldList;

        if ($this->id) {
            $fields = $this->fieldsFromModel($this->id, $fields);
        } else if ($parentId) {
            $fields = $this->fieldsFromParent($parentId);
        }

        foreach ($fields as $fieldName => $fieldValue) {
            if (in_array($fieldName, $this->fieldList)) {
                $fields[$fieldName] = old($fieldName, $fieldValue);
            }
        }

        // Get the additional data for the form fields
        $formFieldData = $this->formFieldData();

        return array_merge(
            $fields,
            $formFieldData
        );
    }

    /**
     * Return the field values from the model.
     *
     * @param int   $id
     * @param array $fields
     *
     * @return array
     */
    protected function fieldsFromModel($id, array $fields)
    {
        $item = $this->model()::findOrFail($id);

        $fieldNames = array_keys($fields);

        $fields = [
            'id' => $id,
        ];
        foreach ($fieldNames as $field) {
            $fields[$field] = $item->{$field};
        }

        return $fields;
    }

    /**
     * Return the field values from the parent.
     *
     * @param int   $parentId
     *
     * @return array
     */
    protected function fieldsFromParent($parentId)
    {
        $item = $this->model()::findOrFail($parentId);

        return array_merge($this->fieldList, [
            'parent_id'       => $item->id,
        ]);
    }

    /**
     * Get the additonal form fields data.
     *
     * @return array
     */
    protected function formFieldData()
    {
        return [];
    }
}

