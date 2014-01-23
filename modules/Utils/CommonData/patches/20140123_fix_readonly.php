<?php
$keys = array(
    'crm_assets_monitor_type',
    'crm_assets_printer_type',
    'CRM/Roundcube',
    'Contacts',
    'Contacts/Access',
    'Countries',
    'Calling_Codes',

    'Contact',
    'Contact/Skills',
    'CADES',
    'CADES/Appointments',
    'CADES/Appointments/Type',
    'CADES/Appointments/Status',
    'CADES/Diagnosis',
    'CADES/Hospitalizations',
    'CADES/Hospitalizations/Methods',
    'CADES/Immunizations',
    'CADES/Immunizations/Types',
    'CADES/Incidents',
    'CADES/Incidents/Types',
    'CADES/Incidents/Status',
    'CADES/Insurance',
    'CADES/Insurance/Types',
    'CADES/Issues',
    'CADES/Issues/Types',
    'CADES/Issues/Status',
    'CADES/MedicalTests',
    'CADES/MedicalTests/Types',
    'CADES/Program',
    'CADES/TerminationReason',
    'CADES/Reviews',
    'CADES/Reviews/Types',
    'Custom',
    'Custom/CADES',
    'Custom/CADES/Authorization',
    'Custom/CADES/Authorization/Service_Group',
    'Custom/CADES/Authorization/Service_Type',
    'Custom/CADES/Authorization/Units_Type',
    'Custom/CADES/Authorization/Status',
    'Custom/CADES/Authorization/Billing/Status',
    'CADES/Seizures',
    'CADES/Seizures/Color',
    'CADES/Seizures/Consciousness',
    'CADES/Seizures/Eyes',
    'CADES/Seizures/Posture',
    'CADES/Seizures/After',
    'CADES/Seizures/Breathing',
    'CADES/Seizures/Movement',
    'CADES/Seizures/Extremities',
    'CADES/Toileting',
    'CADES/Toileting/EventPlace',
    'CADES/Toileting/StoolTypes',
    'CADES/Toileting/StoolSize',
    'CADES/WorkOrders',
    'CADES/WorkOrders/Status',
    'CCN',
    'CCN/CO_Categories',
    'CCN/Sales_Categories',
    'CCN_Financial',
    'CCN_Financial/Cost_of_sales_categories',
    'CCN_Financial/Overhead_exp_categories',
    'ess_installation_status',
    'ess_download_status',
    'ess_confirmation_status',
    'ess_log_message_type',
    'Contacts',
    'Contacts/Gender',
    'Contacts/Categories',
    'Premium',
    'Premium/SalesOpportunity',
    'Premium/SalesOpportunity/Status',
    'Custom/Lyrba',
    'Custom/Lyrba/CargoTracker',
    'Custom/Lyrba/CargoTracker/Cargo_Status',
    'Custom/Lyrba/ContainerTracker',
    'Custom/Lyrba/ContainerTracker/Container_Origin',
    'Custom/Lyrba/ContainerTracker/Pallet_Weight',
    'Custom/Lyrba/ContainerTracker/Superbag_Weight',
    'Custom/Lyrba/ContainerTracker/Container_Material',
    'CompanyStatus',
    'ChangeOrder_Type',
    'ChangeOrder_JobType',
    'Equipment_Power_Type',
    'ZSI_Work_Code',
    'Contacts_skills',
    'Projects',
    'Projects/Visit',
    'Premium',
    'Premium/Apartments',
    'Status',
    'Premium/Checklist',
    'Premium/Checklist/Recurrence',
    'GC_Work_Code',
    'GeneralContractor',
    'GeneralContractor/Visit',
    'Premium/ListManager',
    'Medical',
    'Medical/Appointments',
    'Medical/Appointments/Type',
    'Medical/Diagnosis',
    'Medical/Hospitalizations',
    'Medical/Immunizations',
    'Medical/Immunizations/Types',
    'Medical/Insurance/Types',
    'Medical/Issues',
    'Medical/Issues/Types',
    'Medical/Program',
    'Medical/TerminationReason',
    'Medical/Seizures',
    'Medical/Seizures/Color',
    'Medical/Seizures/Consciousness',
    'Medical/Seizures/Eyes',
    'Medical/Seizures/Posture',
    'Medical/Seizures/After',
    'Medical/Seizures/Breathing',
    'Medical/Seizures/Movement',
    'Medical/Seizures/Extremities',
    'Medical/Toileting',
    'Medical/Toileting/EventPlace',
    'Medical/Toileting/StoolTypes',
    'Medical/Toileting/StoolSize',
    'Premium/Optician',
    'Payments',
    'Premium/Ticket',
    'Premium/Ticket/Testing',
    'Premium/SchoolRegister',
    'Premium/ServiceCredits',
    'Premium/SimpleInvoice',
    'Premium/SimpleInvoice/Payment_Types',
    'Premium/SimpleInvoice/Numbering_Types',
    'Premium/SimpleInvoice/Numbering_Types/0',
    'Premium/SimpleInvoice/Numbering_Types/1',
    'Premium/Training',
    'Premium/Training/Training_Type',
    'Premium/Training/Training_Type/0',
    'Premium/Training/Training_Type/1',
    'Premium/Training/Training_Status/planned',
    'Premium/Training/Training_Status/inprogress',
    'Premium/Training/Training_Status/completed',
    'Premium/Training/Training_Status/billed',
    'Premium/Training/Training_Status/canceled',
    'Premium/Vehicle',
    'Premium/Vehicle/Classification',
    'Premium/Vehicle/Availability_Type',
    'Premium/Vehicle/Availability_Type/0',
    'Premium/Vehicle/Availability_Type/1',
    'Premium/Warehouse',
    'Premium/Warehouse/eCommerce',
    'Premium/Warehouse/eCommerce/Languages',
    'Premium/Warehouse/eCommerce/CompareServices',
    'Premium/Warehouse/eCommerce/CompareServices/ceneo',
    'Premium/Warehouse/eCommerce/CompareServices/skapiec',
    'Premium_Warehouse_Items_Type',
    'Premium_Warehouse_Items_Categories',
    'Premium_Items_Orders_Terms',
    'Premium_Items_Orders_Shipment_Types',
    'Premium_Items_Orders_Payment_Types',
    'Premium_Items_Orders_Trans_Types',
    'Premium_Items_Orders_TaxCalc',
    'Premium_Items_Orders_TaxCalc/0',
    'Premium_Items_Orders_TaxCalc/1',
);

foreach($keys as $key) {
    $id = Utils_CommonDataCommon::get_id($key);
    if($id) DB::Execute('UPDATE utils_commondata_tree SET readonly=%b WHERE id=%d',array(true,$id));
}