$(function(){
	//Patient Listing DataTables
	var oTable = $('#patient_listing').dataTable({
			        "bProcessing": true,
			        "sAjaxSource": 'patient_management/get_patients',
			        "bJQueryUI" : true,
					"sPaginationType" : "full_numbers",
					"bStateSave" : true,
					"sDom" : '<"H"T<"clear">lfr>t<"F"ip>',
					"bAutoWidth" : false,
					"bDeferRender" : true,
					"bInfo" : true,
					"aoColumnDefs": [{ "bSearchable": true, "aTargets": [0,1,3,4] }, { "bSearchable": false, "aTargets": [ "_all" ] }]
			    });

    //Filter Table
    oTable.columnFilter({ 
        aoColumns: [{ type: "text"},{ type: "text" },null,{ type: "text" },{ type: "text" },null]}
    );

    //Fade Out Message
    setTimeout(function(){
		$(".message").fadeOut("2000");
    },6000);
});