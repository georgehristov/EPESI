var Utils_Overview__layout = {
	id : "#Utils_Overview__layout",
	center_id : "#Utils_Overview__table",
	state: null,
	instance: null,
	calculate_height : function() {		
		var offset = jq(Utils_Overview__layout.id).offset();
		var min_height = 450;
		
		var height = Math.max(jq(window).height() - offset.top - 30, min_height);
		
		return height;
	},
	window_resize : function() {
		if (!jq(Utils_Overview__layout.id).length) return;
		
		jq(Utils_Overview__layout.id).height(Utils_Overview__layout.calculate_height());
		
		Utils_Overview__layout.resize_all(Utils_Overview__layout.instance);		
	},
	center_resize : function() {
		Utils_Overview__layout.save_state(Utils_Overview__layout.instance);
	},
	save_state : function(Instance) {
		Utils_Overview__layout.state = Instance.readState("north.size,south.size,east.size,west.size,"
						+ "north.isClosed,south.isClosed,east.isClosed,west.isClosed,"
						+ "north.isHidden,south.isHidden,east.isHidden,west.isHidden");
	},
	load_state : function(Instance, state, options, name) {
		Instance.loadState(Utils_Overview__layout.state, false);
	},
	resize_all : function(Instance) {
		var state = Utils_Overview__layout.state;		
		Instance.resizeAll();		
		Utils_Overview__layout.state = state;
		
		Utils_Overview__layout.load_state(Instance);
	},
	init : function(custom_layout_opts) {
		if (!jq(this.id).length) return;

		jq(this.id).show();
		
		var layout_opts = {
				applyDefaultStyles : true,
				center__onresize : this.center_resize,
				stateManagement__enabled : true,
				stateManagement__autoLoad : false,
				stateManagement__autoSave : false,
				onload : this.load_state,
				onunload : this.save_state,
				height : this.calculate_height()
			}

		jq.extend(layout_opts, custom_layout_opts);

		Utils_Overview__layout.instance = jq(this.id).layout(layout_opts);	
		
		jq(this.center_id).css("padding", "2px");		

		jq(window).resize(Utils_Overview__layout.window_resize);

		Utils_Overview__layout.window_resize();
	}
}
