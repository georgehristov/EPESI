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
			
	},
	permissions: {
		crits_initialized: false,
		clearance: 0,
		crits_ors: {},
		crits_ands: 0,
		clearance_max: 0,
		crits_ors_max: 0,
		crits_ands_max: 0,

		field_values: {"":{}},
		field_sub_values: {},
		update_field_values: function (row, j) {
			var list = jq('#crits_'+row+'_'+j+'_value');
			if (!list) return;
			for(i = (list.length-1); i >= 0; i--) {
				list.options[i] = null;
			}
			i = 0;
			selected_field = jq('#crits_'+row+'_'+j+'_field').val();
			for(k in Utils_RecordBrowser.permissions.field_values[selected_field]) {
				list.options[i] = new Option();
				list.options[i].value = k;
				list.options[i].text = Utils_RecordBrowser.permissions.field_values[selected_field][k];
				i++;
			}
			Utils_RecordBrowser.permissions.update_field_sub_values(row, j);
		},
		update_field_sub_values: function (row, j) {
			var list = jq('#crits_'+row+'_'+j+'_sub_value');
			if (!list) return;
			selected_field = jq('#crits_'+row+'_'+j+'_field').val();
			selected_value = jq('#crits_'+row+'_'+j+'_value').val();
			for(i = (list.length-1); i >= 0; i--) {
				list.options[i] = null;
			}
			if (Utils_RecordBrowser.permissions.field_sub_values[selected_field+'__'+selected_value]) {
				i = 0;
				for(k in Utils_RecordBrowser.permissions.field_sub_values[selected_field+'__'+selected_value]) {
					list.options[i] = new Option();
					list.options[i].value = k;
					list.options[i].text = Utils_RecordBrowser.permissions.field_sub_values[selected_field+'__'+selected_value][k];
					i++;
				}
				jq('#crits_'+row+'_'+j+'_sub_value').show();
			} else {
				jq('#crits_'+row+'_'+j+'_sub_value').hide();
			}
		},
		init_clearance: function (current, max) {
			if (!Utils_RecordBrowser.permissions.crits_initialized) 
				Utils_RecordBrowser.permissions.clearance = current;
			Utils_RecordBrowser.permissions.clearance_max = max;
			if (Utils_RecordBrowser.permissions.clearance+1==Utils_RecordBrowser.permissions.clearance_max)
				jq('#add_clearance').hide();
			for (i=0; i<max; i++)
				jq('#div_clearance_'+i).toggle(i<=Utils_RecordBrowser.permissions.clearance);
		},
		add_clearance: function () {
			Utils_RecordBrowser.permissions.clearance++;
			if (Utils_RecordBrowser.permissions.clearance+1==Utils_RecordBrowser.permissions.clearance_max)
				jq('#add_clearance').hide();
			jq('#div_clearance_'+Utils_RecordBrowser.permissions.clearance).show();
		},

		init_crits_and: function (current, max) {
			if (!Utils_RecordBrowser.permissions.crits_initialized) 
				Utils_RecordBrowser.permissions.crits_ands = current;
			if (Utils_RecordBrowser.permissions.crits_ands+1==Utils_RecordBrowser.permissions.crits_ands_max)
				jq('add_and').hide();
			Utils_RecordBrowser.permissions.crits_ands_max = max;
			for (i=0; i<max; i++)
				jq('#div_crits_row_'+i).toggle(i <= Utils_RecordBrowser.permissions.crits_ands);
		},
		add_and: function () {
			Utils_RecordBrowser.permissions.crits_ands++;
			if (Utils_RecordBrowser.permissions.crits_ands+1==Utils_RecordBrowser.permissions.crits_ands_max)
				jq('#add_and').hide();
			jq('#div_crits_row_'+Utils_RecordBrowser.permissions.crits_ands).show();
		},
		init_crits_or: function (row, current, max) {
			if (!Utils_RecordBrowser.permissions.crits_initialized) 
				Utils_RecordBrowser.permissions.crits_ors[row] = current;
			if (Utils_RecordBrowser.permissions.crits_ors[row]+1==Utils_RecordBrowser.permissions.crits_ors_max)
				jq('#add_or_'+row).hide();
			Utils_RecordBrowser.permissions.crits_ors_max = max;
			for (i=0; i<max; i++)
				jq('#div_crits_or_'+row+'_'+i).toggle(i <= Utils_RecordBrowser.permissions.crits_ors[row]);
		},
		add_or: function (row) {
			Utils_RecordBrowser.permissions.crits_ors[row]++;
			if (Utils_RecordBrowser.permissions.crits_ors[row]+1==Utils_RecordBrowser.permissions.crits_ors_max)
				jq('#add_or_'+row).hide();
			jq('#div_crits_or_'+row+'_'+Utils_RecordBrowser.permissions.crits_ors[row]).show();
		},
		set_field_access_titles: function (labels_map) {
			if (jq(".permissions_option_title").length) return;

			jq.each(labels_map, function(id, label) {
				jq("#"+id).prepend('<option disabled class="permissions_option_title">' + label + '</option>').height('300px');
			});
		}

	}
}
