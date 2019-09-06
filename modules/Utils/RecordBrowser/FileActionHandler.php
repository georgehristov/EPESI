<?php

require_once __DIR__ . '/../FileStorage/ActionHandler.php';

class Utils_RecordBrowser_FileActionHandler
    extends Utils_FileStorage_ActionHandler
{
    protected function getHandlingScript()
    {
        return get_epesi_url() . '/modules/Utils/RecordBrowser/file.php';
    }

    /**
     * Get Action urls for RB file leightbox
     *
     * @param int    $filestorageId Filestorage ID
     * @param string $tab           Recordset name. e.g. company
     * @param int    $recordId      Record ID
     * @param string $field         Field identifier. e.g. company_name
     *
     * @return array
     */
    public function getActionUrlsRB($filestorageId, $tab, $recordId, $field)
    {
        $params = ['tab' => $tab, 'record' => $recordId, 'field' => $field];
        return $this->getActionUrls($filestorageId, $params);
    }

    protected function hasAccess($action, $request)
    {
        $tab = $request->get('tab');
        $recordId = $request->get('record');
        $field = $request->get('field');
        $filestorageId = $request->get('id');
        if (!($tab && $recordId && $field && $filestorageId)) {
            return false;
        }
        
        $record = Utils_RecordBrowser_Recordset::create($tab)->findOne($recordId);
        
        if (! $field = $record->getRecordset()->getField($field)) return false;
        
        return $record->getUserFieldAccess($field) && in_array($filestorageId, $record[$field->getId()]);
    }
}
