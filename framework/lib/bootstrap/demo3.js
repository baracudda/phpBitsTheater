;(function() {
	function guid() {
		function _p8(s) {
			var p = (Math.random().toString(16)+"000000000").substr(2,8);
			return s ? "-" + p.substr(0,4) + "-" + p.substr(4,4) : p ;
		}
		return _p8() + _p8(true) + _p8(true) + _p8();
	}
	
	
	
     
	function saveFlowchart(){
            var nodes = []
			$(".w").each(function (idx, elem) {
				var $elem = $(elem);
				var endpoints = jsPlumb.getEndpoints($elem.attr('id'));
			
				nodes.push({
					blockId: $elem.attr('id'),
					nodetype: $elem.attr('data-nodetype'),
					positionX: parseInt($elem.css("left"), 10),
					positionY: parseInt($elem.css("top"), 10),
					titleText: $elem.find(".ww").text(),
					bodyText: $elem.find(".ep").text()					
				});
			});
			iCounter = 0;
			var connections = [];
			
			$.each(jsPlumb.getConnections(), function (idx, connection) {
				var mySourceId = '#' + connection.sourceId;
				connections.push({
					connectionLabel: connection.getOverlay(connection.getParameter('idIST')).getLabel(),
					connectionId: connection.getParameter('idIST'), //connection.id,
					pageSourceId: $(mySourceId).parent().attr("id"),
					pageTargetId: connection.targetId,
					anchors: $.map(connection.endpoints, function(endpoint) {
						return [[endpoint.anchor.x, 
						endpoint.anchor.y/*, 
						endpoint.anchor.orientation[0], 
						endpoint.anchor.orientation[1],
						endpoint.anchor.offsets[0],
						endpoint.anchor.offsets[1]*/]];
					})
				});
			});
	
			var flowChart = {};
			flowChart.nodes = nodes;
			flowChart.connections = connections;
			
			var flowChartJson = JSON.stringify(flowChart);
			//console.log("edsave: " + flowChartJson);
			$('#jsonOutput').val(flowChartJson);
        };
		
		function loadFlowchart(){
			jsPlumb.Defaults.Container = "statemachine-demo"
			jsPlumb.Defaults.ConnectionOverlays = [
				[ "PlainArrow", { 
					location:1,
					id:"arrow",
                    length:12,
                    foldback:1,
					width:12
				} ],
                [ "Label", {  cssClass:"aLabel" }]
			];
            var flowChartJson = $('#jsonOutput').val();
            var flowChart = JSON.parse(flowChartJson);
            var nodes = flowChart.nodes;
            $.each(nodes, function( index, elem ) {
				//console.log(elem);
				//repositionElement(id, elem.positionX, elem.positionY);
				
				var newState = $('<div>').attr('id', elem.blockId).addClass('w');
				var title = $('<div class="ww">'+elem.titleText+'</div>');
				var connect = $('<div class="ep">'+elem.bodyText+'</div>');
				newState.css({
				  'top': elem.positionY,
				  'left': elem.positionX
				});
				jsPlumb.makeTarget(newState, {
				  dropOptions:{ hoverClass:"dragHover" },
				  anchor: 'Continuous'			  
				});
				jsPlumb.makeSource(connect, {
					anchor:"Continuous",
					connector:[ "Flowchart" ],
					connectorStyle:{ strokeStyle:"#225588", lineWidth:4, outlineColor:"transparent", outlineWidth:4 },
				});
					
				newState.append(title);
				newState.append(connect);
				
				$('#statemachine-demo').append(newState);
				
				jsPlumb.draggable(newState, {
				  containment: 'parent'
				});
				
            });

            var connections = flowChart.connections;
            $.each(connections, function( index, elem ) {
				console.log(elem);
                
				var connection1 = jsPlumb.connect({
						source: elem.pageSourceId,
						target: elem.pageTargetId,
						label: elem.connectionLabel,
						id: elem.connectionId,
						connector: "Flowchart",
						anchors: ["BottomCenter", [0.75, 0, 0, -1]],
						overlays:[ 
							"PlainArrow", 
							[ "Label", { location:1,
								id:"arrow",
								length:12,
								foldback:1,
								width:12,
								cssClass:"aLabel"
 							}]
						],
						connectorOverlays:[ 
							[ "Arrow", { width:10, length:30, location:1 } ],
							[ "Label", { label:"foo" } ]
						],
						
						/*connectorStyle:{ strokeStyle:"#225588", lineWidth:4, outlineColor:"transparent", outlineWidth:4 },*/
				});
				alert("");
				
				//connectorStyle:{ strokeStyle:"#225588", lineWidth:4, outlineColor:"transparent", outlineWidth:4 },
				/*jsPlumb.select(connection1).addOverlay(
						
						["Label", {
							label: elem.connectionLabel,
							location: 0.5,
							cssClass: 'aLabel',
							id: elem.connectionId
						}],
						[ "PlainArrow", { 
							location:1,
							id:"arrow",
							length:12,
							foldback:1,
							width:12
						}]
				);*/
				
				
				
				/* think i need to uncomment this
				jsPlumb.bind("connection", function(e) { 
					var con=e.connection;
					con.setParameter('idIST',elem.connectionId); 
	

						jsPlumb.select(e).addOverlay(
							["Label", {
							//label: uuid_answer,
							location: 0.5,
							cssClass: 'aLabel',
							//id: uuid_answer
							}]
						);					
				});*/
				//$("#edialog-form").dialog("close");
				
            });
			
            //numberOfElements = flowChart.numberOfElements;
        };
		
		function saveSurvey() {
			var my_surveyname = $('#fld_surveyname').val();
			var my_optintext  = $.trim($('#fld_optintext').val());
			
			var uuid_survey = guid();
			alert (uuid_survey);
			alert (my_surveyname);
			alert(my_optintext);
			alert ('INSERT INTO `webapp_sms_surveys` (`survey_id`, `name`, `optin_text`) VALUES ("' + uuid_survey + '", "' + my_surveyname + '", "' + my_optintext + '");');
			console.log('INSERT INTO `webapp_sms_surveys` (`survey_id`, `name`, `optin_text`) VALUES ("' + uuid_survey + '", "' + my_surveyname + '", "' + my_optintext + '");');
			// was here
			var iCounter = 0;
			
			var nodes = []
			$(".w").each(function (idx, elem) {
			var $elem = $(elem);
			var endpoints = jsPlumb.getEndpoints($elem.attr('id'));
			console.log('endpoints of '+$elem.attr('id'));
			
			iCounter = iCounter + 1;
			
				nodes.push({
					blockId: $elem.attr('id'),
					nodetype: $elem.attr('data-nodetype'),
					positionX: parseInt($elem.css("left"), 10),
					positionY: parseInt($elem.css("top"), 10),
					titleText: $elem.find(".ww").text(),
					bodyText: $elem.find(".ep").text()					
				});
				// questions (blockId)
				if (iCounter == 1) {
					var myquery = 'INSERT INTO `webapp_sms_surveys` (`survey_id`, `name`, `optin_text`) VALUES ("' + uuid_survey + '", "' + 'TestSurveyName2' + '", "' + $elem.find(".ww").text() + '");';
					$.post("saveSurvey.php", { surveyquery: myquery});
					alert("Opt-in text: " +  $elem.find(".ww").text());
				}

				console.log('INSERT INTO `webapp_sms_survey_questions` (`question_id`, `survey_id`, `rank`, `text`) VALUES ("' + $elem.attr('id') + '", "' + uuid_survey + '", ' + iCounter + ', "' + $elem.find(".ep").text() + '")');
				var myquery = 'INSERT INTO `webapp_sms_survey_questions` (`question_id`, `survey_id`, `rank`, `text`) VALUES ("' + $elem.attr('id') + '", "' + uuid_survey + '", ' + iCounter + ', "' + $elem.find(".ep").text() + '")';
				$.post("saveSurvey.php", { surveyquery: myquery});
			
			});
			iCounter = 0;
			var connections = [];
			$.each(jsPlumb.getConnections(), function (idx, connection) {
				var mySourceId = '#' + connection.sourceId;
				iCounter = iCounter + 1;
			
				console.log("Label is currently " + connection.getOverlay(connection.getParameter('idIST')).getLabel());
				console.log("id: " + connection.getParameter('idIST'));
				connections.push({
					connectionLabel: connection.getOverlay(connection.getParameter('idIST')).getLabel(),
					connectionId: connection.getParameter('idIST'), //connection.id,
					pageSourceId: $(mySourceId).parent().attr("id"),
					pageTargetId: connection.targetId
				});
				// answers (connectionLabel)
				console.log('INSERT INTO `webapp_sms_survey_question_answers` (`answer_id`, `question_id`, `survey_id`, `next_question_id`, `rank`, `text`) VALUES ("' + connection.getParameter('idIST') + '", "' + $(mySourceId).parent().attr("id") + '", "' + uuid_survey + '", "' + connection.targetId + '", ' + iCounter + ', "' +  connection.getOverlay(connection.getParameter('idIST')).getLabel() + '")');
				var myquery = 'INSERT INTO `webapp_sms_survey_question_answers` (`answer_id`, `question_id`, `survey_id`, `next_question_id`, `rank`, `text`) VALUES ("' + connection.getParameter('idIST') + '", "' + $(mySourceId).parent().attr("id") + '", "' + uuid_survey + '", "' + connection.targetId + '", ' + iCounter + ', "' +  connection.getOverlay(connection.getParameter('idIST')).getLabel() + '")';
				$.post("saveSurvey.php", { surveyquery: myquery});
			
			});
	
			var flowChart = {};
			flowChart.nodes = nodes;
			flowChart.connections = connections;
			//flowChart.numberOfElements = numberOfElements;
			
			var flowChartJson = JSON.stringify(flowChart);
			console.log("ed: " + flowChartJson);
	
			//return false;
		} //shouldISave
	
	$("#btn_save").click( function()
           {
             saveFlowchart();
			 
           }
      )
	  $("#btn_load").click( function()
           {
             loadFlowchart();
			 
           }
      )
	  

	jsPlumb.ready(function() {
					
		//var i = 0;
		jsPlumb.Defaults.Container = "statemachine-demo"
		jsPlumb.Defaults.ConnectionOverlays = [
				[ "PlainArrow", { 
					location:1,
					id:"arrow",
                    length:12,
                    foldback:1,
					width:12
				} ],
                [ "Label", {  cssClass:"aLabel" }]
			];
			
		//$(document).on("contextmenu", ".w", function(e){
		$(document).on("contextmenu", "#statemachine-demo", function(e){
			//var my_surveyname = $('#fld_surveyname').val();
			
			//var myquery = 'select * from `webapp_sms_surveys` WHERE `name` = ' + my_surveyname;
			//console.log ($.post("saveSurvey.php", { surveyquery: myquery}));
			
			var my_optintext  = $.trim($('#fld_optintext').val());
			
			if(my_optintext != '') {
				$.ajax({
					url : 'checkOptInText.php',
					data : {name_optin : my_optintext},
					dataType : 'JSON',
					type : 'POST',
					cache : false,

					success : function(result) {
					if(result == '1') { alert('Opt In Text Already Exists.'); }
						else if(result == '0') { saveSurvey(); }
					},

					error : function(err) {
					console.log(err);
					}
				});
			}
			
			
			
			
			//
			
			//alert("Opt-in text: " +  $elem.find(".ww").text());
			// main survey data
			
			
		});
		 
		
		
		jsPlumb.bind("connection", function(e) { 
			
			$('#edialog-form').modal('toggle');	
			$("#fld_answer").val("");
			$('#btn_saveanswer').unbind("click"); // need to remove/re-factor epr
			$('#btn_saveanswer').click(function() {
				var uuid_answer = guid();
				val_answer=$("#fld_answer").val();
				
				console.log("add connection: " + uuid_answer);
				
				var con=e.connection;
				con.setParameter('idIST',uuid_answer); 
				
				jsPlumb.select(e).addOverlay(/*[ "Arrow", { foldback:0.2, location:0.75, width:25 } ],*/
					["Label", {
					label: uuid_answer,
					location: 0.5,
					cssClass: 'aLabel',
					id: uuid_answer
					}]
				);
				e.connection.getOverlay(uuid_answer).setLabel(val_answer); 
				uuid_answer = "";
			});
		});
		
		
		
		$('#statemachine-demo').dblclick(function(e) {	
			$('#ddialog-form').modal('toggle');				
			$("#fld_title").val("");
			$("#fld_body").val("");
			
			$('#btn_savequestion').unbind("click"); // need to remove/re-factor epr
			$('#btn_savequestion').click(function(){				
			   val_title=$("#fld_title").val();
			   val_textbody=$("#fld_body").val();
			   //if(val_title!='Close2'){
					var uuid = guid();
					//continue the processing
					var newState = $('<div>').attr('id', uuid).addClass('w');
					var title = $('<div class="ww">'+val_title+'</div>');
					var connect = $('<div class="ep">'+val_textbody+'</div>');
					//$('<div>').addClass('w').text('State ' + i);			
					//var connect = $('<div>').addClass('connect');
					//var connect = $('<div>').addClass('ep');
					
					
					newState.css({
					  'top': e.pageY,
					  'left': e.pageX
					});
					
					
					//jsPlumb.makeTarget(newState, {
					jsPlumb.makeTarget(newState, {
					  dropOptions:{ hoverClass:"dragHover" },
					  anchor: 'Continuous'			  
					});
					
					//jsPlumb.makeSource(connect, {
					jsPlumb.makeSource(connect, {
					  //parent: newState,
					  //anchor: 'Continuous'
							anchor:"Continuous",
							// connector:[ "StateMachine", { curviness:20 } ], epr
							connector:[ "Flowchart" ],
							connectorStyle:{ strokeStyle:"#225588", lineWidth:4, outlineColor:"transparent", outlineWidth:4 },
							
							
					});
					
					newState.append(title);
					newState.append(connect);
					
					$('#statemachine-demo').append(newState);
					
					//i++;    
					
					jsPlumb.draggable(newState, {
					  containment: 'parent'
					});
				
				// re-add epr
				//	newState.dblclick(function(e) {
				//	  jsPlumb.detachAllConnections($(this));
				//	  $(this).remove();
				//	  e.stopPropagation();
				//	});
			   //}
				newState = "";
				//alert ("newstate");
			})		
			
			
		  }); 
		
	});
})();