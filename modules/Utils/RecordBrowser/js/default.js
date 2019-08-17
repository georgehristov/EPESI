Utils_RecordBrowser = {
	jump: function (tab) {
		jq('#jump_to_record_input').toggle().focus();
	},
	history: {
		field: 'historical_view_pick_date',
		load: function (tab, id, form_name) {
			jq.ajax({
				type: 'post',
				url: 'modules/Utils/RecordBrowser/edit_history.php', 
				data:{
					tab: tab,
					id: id,
					date: document.forms[form_name].elements[this.field].value,
					cid: Epesi.client_id
				},
				success:function(response) {
					eval(response);
				}
			});
		},
		jump: function (selected_date, tab, id, form_name) {
			jq('#' . this.field).val(selected_date);
				
			Utils_RecordBrowser.history.load(tab, id, form_name);
		}
	},
	setFavorite: function (state, tab, id, element) {
		jq('#' . element).html('...');
			
		new jq.ajax({
			type: 'post',
			url: 'modules/Utils/RecordBrowser/favorites.php',
			data:{
				tab: Object.toJSON(tab),
				id: Object.toJSON(id),
				state: Object.toJSON(state),
				element: Object.toJSON(element),
				cid: Epesi.client_id
			},
			success: function(response) {
				eval(response);
			}
		});
	},
	index: function (token) {
	    jq.getScript('modules/Utils/RecordBrowser/indexer.php?cid=' + Epesi.client_id + '&token=' + token);
	},
	c2f: {
			
	}
}

