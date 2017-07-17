<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.event.plugin');

class plgFlexicontent_fieldsBusinesshours extends JPlugin
{
	static $field_types = array('businesshours');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsBusinesshours( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_businesshours', JPATH_ADMINISTRATOR);
	}


	//function 
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		
		// ****************
		// Number of values
		// ****************
		$multiple   = 1; 	// TODO $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = 0; 	// TODO $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = 0;	// TODO (int) $field->parameters->get( 'add_position', 3 ) ;

		//get additional option parameters
		$hide_minutes = (int) $field->parameters->get( 'hide_minutes', 0 ) ;
		$hours_format = (int) $field->parameters->get( 'hours_format', 12 ) ;
		$short_day 	= $field->parameters->get( 'short_day', 0 ) ;
		$weekday_start 	= $field->parameters->get( 'weekday_start', 0 ) ;
		$minute_interval = $field->parameters->get( 'minute_interval', 30 ) ;
		$show_freetext = $field->parameters->get( 'show_freetext', 1 ) ;

		$hour_options = array();
		
		//create a list of hours dropdown based on minute_interval
		$i = 0;
		while($i < 2400){
			//pad the hour so that its always a 4 character string, for easier check
			$thedata = str_pad($i, 4, '0', STR_PAD_LEFT);

			//add the display text for the hour
			$hour_options[$thedata] = $thedata[0] . $thedata[1] . ':' . $thedata[2] . $thedata[3];

			//check the hours_format and revise hours option display text on the dropdown if its base 12
			if ($hours_format == 12){
				//set AM or PM
				$ampm = ' AM';
				
				if($i >= 1200) $ampm = ' PM';

				$hour_options[$thedata] .= $ampm;

				//handle 12AM
				if ($i < 100){
					$hour_options[$thedata][0] = '1';
					$hour_options[$thedata][1] = '2';
				//handle > 12PM
				}
				else if ($i >= 1300){
					//need to subtract by 12
					$pmhours = str_pad(((int) $hour_options[$thedata][0] . $hour_options[$thedata][1] - 12), 2, '0', STR_PAD_LEFT);
					//update the hour option display data
					$hour_options[$thedata][0] = $pmhours[0];
					$hour_options[$thedata][1] = $pmhours[1];
				}
			}

			//increment counter by minute interval value
			$i += $minute_interval;

			//check if iterator $i reached 60minutes/1 hours, and set it forward if it is
			$current_minute_value = substr((string)$i,-2);
			if ($current_minute_value == 60)
				// force bump to next hour
				$i += 40;
		}

		$selected_start = 'mo';
		$selected_end = 'fr';
		
		//flag for empty data, used for showing 1 row disabled when data is empty on edit mode page load
		//and disabling row when deleting last row remaining
		$dataEmpty = false;
		
		$values = array();
		// first try to unserialize the data
		if(count($field->value)) {
			foreach($field->value as $value) {
				$value = unserialize($value);
				if(is_array($value)) $values[] = $value;
			}
		}
		
		if(!count($values)) {
			$values[] = array('day_radio_options' => '', 'days' => '', 'hours' => '');
			$dataEmpty = true;
		}

		//init day options, weekday starts monday
		$day_options = array(
			JHTML::_('select.option', 'su', JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SUNDAY') ),
			JHTML::_('select.option', 'mo', JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_MONDAY') ),
			JHTML::_('select.option', 'tu', JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_TUESDAY') ),
			JHTML::_('select.option', 'we', JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_WEDNESDAY') ),
			JHTML::_('select.option', 'th', JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_THURSDAY') ),
			JHTML::_('select.option', 'fr', JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_FRIDAY') ),
			JHTML::_('select.option', 'sa', JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SATURDAY') )
		);
		//re-arrange entries if weekday starts on monday
		if ($weekday_start == 0) {
			array_shift($day_options); 
			array_push($day_options, JHTML::_('select.option', 'su', JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SUNDAY') ));
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$option_radio_class = 'use_prettycheckable day_radio_option0 day_radio';

		$business_hours_checkbox_attributes = 'style="width:120px"';
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;
		
		$js = "";
		$css = "";

		//custom css specific for businesshours field
		
		$business_hours_css="
			.business-hours-item {
				border:1px solid #eee; 
				padding:10px; 
				margin-bottom:10px; 
				float:left;
			}
			.business-hours-radio-options {
				margin-right:10px; 
				width:200px; 
				float:left;
			}
			.day-container{
				width:300px;
				float:left; 
			}
			.hour-container{
				width:230px;
				float:left;
			}
			.hour-range-container{
				width:230px;
				float:left;
			}
			
		";
		// Add the drag and drop sorting feature
		if (!$use_ingroup) $js .= "
		jQuery(document).ready(function(){
			jQuery('#sortables_".$field->id."').sortable({
				handle: '.fcfield-drag-handle',
				containment: 'parent',
				tolerance: 'pointer'
			});
		});
		";
		
		//create the single Day Dropdown HTML code, the str_replace is to add \ on every line break to conform javascript multi line string.
		$single_day_dropdown = '<span class="flexi label sub_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SELECT_DAY').'</span><br />' 
			. str_replace("\n", "", JHTML::_('select.genericlist', $day_options, 'theFieldName', ' class="day_single" data-uniqueRowNum="theUniqueRowNum"', 'value', 'text', $selected_start, 'theElementId'));
		//multi day dropdown
		
		$day_range_dropdown = '<span class="flexi label sub_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SELECT_START_END_DAYS').'</span><br />'
			. str_replace("\n", "", 
				JHTML::_('select.genericlist', $day_options, $fieldname.'[0][day_range_start]', ' class="day_range_start" data-uniqueRowNum="theUniqueRowNum"', 'value', 'text', $selected_start, $fieldname.'_0_day_range_start'). ' - ' .
				JHTML::_('select.genericlist', $day_options, $fieldname.'[0][day_range_end]', ' class="day_range_end" data-uniqueRowNum="theUniqueRowNum"', 'value', 'text', $selected_end, $fieldname.'_0_day_range_end')
			);

		//construct default hour range/s HTML (1 row only)
		$hourEntriesDefault = str_replace("\n", "",
			'<span class="flexi label sub_label">'.
				JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SELECT_OPEN_CLOSE_HOURS').
			'</span><br />'.
			'<div class="hour-range-container" style="float:left" data-hourRowNum="0">'.
				JHTML::_('select.genericlist', $hour_options, $fieldname.'[0][hour_open][0]', ' class="hour_open" data-uniqueRowNum="0"', 'value', 'text', '0800', $elementid.'_0_hour_open_0').
				JHTML::_('select.genericlist', $hour_options, $fieldname.'[0][hour_close][0]', ' class="hour_close" data-uniqueRowNum="0"', 'value', 'text', '1700', $elementid.'_0_hour_close_0').
				'<span class="fcfield-delvalue fcfont-icon" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteHourRow'.$field->id.'(jQuery(this));"></span>'.
			'</div>'.
			'<span class="fcfield-addvalue fcfont-icon hourRow" onclick="addHourRow'.$field->id.'(jQuery(this));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>'
			);

		// custom functions 
		$js .= "
			//function to reset businesshours row to default values (range days, mo-fr, 1 hour row, 8-4pm)
		    function businesshoursReset".$field->id." () {
				var elem = jQuery('#sortables_".$field->id."');
				var radioClass = '".$option_radio_class."';
				//reset the day type radio options to range of days
				elem.find('a').attr('class', '');
				elem.find(':input').attr('disabled', false);
				elem.find(':radio').attr('class', radioClass);
				elem.find(':radio').each(function(){
					if(jQuery(this).val() == '2') {
						jQuery(this).siblings('a').attr('class', 'checked');
						jQuery(this).attr('checked', true);
					} else{
						jQuery(this).siblings('a').attr('class', '');
						jQuery(this).attr('checked', false);
					}
				});
				//show the parent div
				elem.slideDown(400, function(){});

				//reset the day option to range of days, 2 dropdowns mo-fr
				elem.find('div.day-container').first().html('".$day_range_dropdown."');

				//reset the hour box, to have only 1 row with value of 8-4
				elem.find('div.hour-container').first().html('".$hourEntriesDefault."');
		    }

		    //disable the businesshours row
		    function businesshoursDisable".$field->id." () {
				var elem = jQuery('#sortables_".$field->id."');
				elem.find('a').attr('class', 'disabled');
				elem.find(':input').attr('disabled', true);
				elem.find(':radio').attr('class', 'disabled');

				//hide the parent div
				elem.slideUp(400, function(){});
				return true;
		    }

		    //update the businesshours row if the data-uniqueRowNum is not 0;
		    function businesshoursUpdateNameAndIds".$field->id." () {
		    	var elem = jQuery('#sortables_".$field->id."');
		    	//find the current row's uniqueRowNum
		    	var dataUniqueRow = elem.find('div.day-container').first().attr('data-uniqueRowNum');
		    	// if 0 do nothing
		    	if( dataUniqueRow == 0) return false;

				//update field attributes
				elem.find('select.day_radio').each(function(){
					var radioElem = jQuery(this);
					var radioVal = jQuery(radioElem).val();
					setAttributeAndValues".$field->id."(radioElem, {name: '" . $fieldname . "['+dataUniqueRow+']".'[day_radio_options]'."', id: '" . $elementid . "_'+dataUniqueRow+'_day_radio_option'+radioVal, rowNum: dataUniqueRow, val: radioVal});
				});
				setAttributeAndValues".$field->id."(elem.find('select.day_range_start').first(), {name: '" . $fieldname . "['+dataUniqueRow+']".'[day_range_start]'."', id: '" . $elementid . "_'+dataUniqueRow+'_day_range_start', rowNum: dataUniqueRow, val: 'mo'});
				setAttributeAndValues".$field->id."(elem.find('select.day_range_end').first(), {name: '" . $fieldname . "['+dataUniqueRow+']".'[day_range_end]'."', id: '" . $elementid . "_'+dataUniqueRow+'_day_range_end', rowNum: dataUniqueRow, val: 'fr'});
				setAttributeAndValues".$field->id."(elem.find('select.hour_open').first(), {name: '" . $fieldname . "['+dataUniqueRow+'][hour_open][0]', id: '" . $elementid . "_'+dataUniqueRow+'_hour_open_0', rowNum: dataUniqueRow });
				setAttributeAndValues".$field->id."(elem.find('select.hour_close').first(), {name: '" . $fieldname . "['+dataUniqueRow+'][hour_close][0]', id: '" . $elementid . "_'+dataUniqueRow+'_hour_close_0', rowNum: dataUniqueRow });

				return true;
		    }

		    //function to update attributes and values for certain number of inputs
		    function setAttributeAndValues".$field->id." (elem, data) {
		    	switch(elem.attr('class')) {
		    		case 'day_range_start':
					case 'day_range_end':
						elem.attr('name', data['name']);
						elem.attr('id', data['id']);
						elem.attr('data-uniqueRowNum', data['rowNum']);
						elem.val(data['val']);
						break;
					
					case 'hour_open':
					case 'hour_close':
						elem.attr('name', data['name']);
						elem.attr('id', data['id']);
						elem.attr('data-uniqueRowNum', data['rowNum']);				
						break;
					
					case 'multi_day':
						elem.attr('name', data['name']);
						elem.attr('id', data['id']);
						elem.attr('class', data['class']);
						elem.attr('data-element-grpid', data['elementGroupId']);
						elem.prev('label').attr('for', data['for']);
						break;
		      	}

		    	if (elem.attr('data-customclass') == 'fcradiocheck') {
					elem.attr('name', data['name']);
					elem.attr('id', data['id']);
					elem.attr('class', data['class']);
					elem.attr('data-element-grpid', data['elementGroupId']);
					elem.prev('label').attr('for', data['for']);
		    	} 

				return true;
		    }
			";

		// Check radio group changes, and make HTML to render the correct day field changes, either dropdown, checkboxes or two dropdowns
		// using .on because we need to check for dynamically created radio group as well
		$js.= "
			jQuery(document).on('change','#sortables_".$field->id." .day_radio', function(e) {				
				//get parent's day container
				var dayContainer = jQuery(this).closest('.fcfieldval_container').children('.business-hours-item').children('.day-container');
				var currentRowNum = dayContainer.attr('data-uniqueRowNum');

				switch(jQuery(this).val()){
					//change field to single dropdown
					case '0': 
						dayContainer.html('".$single_day_dropdown."');
						//update field attributes
						var elem= dayContainer.find('select.day_single').first();
						elem.attr('name', '" . $fieldname . "['+currentRowNum+']".'[day_single]'."');
						elem.attr('id', '" . $elementid . "_'+currentRowNum+'".'_day_single'."');
						elem.attr('data-currentRowNum', currentRowNum);					
						elem.val('".$selected_start."');					
					break;

					//change field to multi-day checkboxes
					case '1': 
						var multi_day_checkbox = '\
						<span class=\"flexi label sub_label\">".JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SELECT_ONE_OR_MORE_DAYS')."</span><br />\
						<fieldset ".$business_hours_checkbox_attributes.">\
							<div class=\"clearfix prettycheckbox labelright blue\"><input class=\"use_prettycheckable \" type=\"checkbox\" name=\"" . $fieldname . "['+currentRowNum+']".'[multi_day][]'."\" id=\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."0\" data-element-grpid=\"custom_businesshours_1\" value=\"mo\" checked=\"checked\"> <label for\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."0\">Monday</label><br></div>\
							<div class=\"clearfix prettycheckbox labelright blue\"><input class=\"use_prettycheckable \" type=\"checkbox\" name=\"" . $fieldname . "['+currentRowNum+']".'[multi_day][]'."\" id=\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."1\" data-element-grpid=\"custom_businesshours_1\" value=\"tu\" > <label for\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."1\">Tuesday</label><br></div>\
							<div class=\"clearfix prettycheckbox labelright blue\"><input class=\"use_prettycheckable \" type=\"checkbox\" name=\"" . $fieldname . "['+currentRowNum+']".'[multi_day][]'."\" id=\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."2\" data-element-grpid=\"custom_businesshours_1\" value=\"we\" > <label for\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."2\">Wednesday</label><br></div>\
							<div class=\"clearfix prettycheckbox labelright blue\"><input class=\"use_prettycheckable \" type=\"checkbox\" name=\"" . $fieldname . "['+currentRowNum+']".'[multi_day][]'."\" id=\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."3\" data-element-grpid=\"custom_businesshours_1\" value=\"th\" > <label for\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."3\">Thursday</label><br></div>\
							<div class=\"clearfix prettycheckbox labelright blue\"><input class=\"use_prettycheckable \" type=\"checkbox\" name=\"" . $fieldname . "['+currentRowNum+']".'[multi_day][]'."\" id=\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."4\" data-element-grpid=\"custom_businesshours_1\" value=\"fr\" > <label for\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."4\">Friday</label><br></div>\
							<div class=\"clearfix prettycheckbox labelright blue\"><input class=\"use_prettycheckable \" type=\"checkbox\" name=\"" . $fieldname . "['+currentRowNum+']".'[multi_day][]'."\" id=\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."5\" data-element-grpid=\"custom_businesshours_1\" value=\"sa\" > <label for\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."5\">Saturday</label><br></div>\
							<div class=\"clearfix prettycheckbox labelright blue\"><input class=\"use_prettycheckable \" type=\"checkbox\" name=\"" . $fieldname . "['+currentRowNum+']".'[multi_day][]'."\" id=\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."6\" data-element-grpid=\"custom_businesshours_1\" value=\"su\" > <label for\"" . $elementid . "_'+currentRowNum+'".'_multi_day'."6\">Sunday</label><br></div>\
						</fieldset>\
						';
						dayContainer.html(multi_day_checkbox);
						
						// Remove HTML added by prettyCheckable JS, from the dupicate new INPUT SET
						// This is only done on prettycheckboxes 
						var prettyContainers = dayContainer.find('.prettyradio, .prettycheckbox');
						prettyContainers.find('input, label').each(function() {
							var el = jQuery(this);
							el.insertAfter(el.parent());
						});
						prettyContainers.remove();

						var js_class = ' use_prettycheckable day_checkbox ';
						
						// Update the new INPUT SET
						var theSet = dayContainer.find('input:checkbox');
						var nr = 0;
						theSet.each(function() {
							var elem = jQuery(this);
							elem.attr('name', '" . $fieldname . "['+currentRowNum+'][multi_day][]');
							elem.attr('id', '" . $elementid . "_'+currentRowNum+'_multi_day_'+nr);
							elem.attr('class', '" . $elementid . "_'+currentRowNum + js_class);
							elem.attr('data-element-grpid', '" . $elementid . "_multi_day_'+currentRowNum);
							elem.prev('label').attr('for', '" . $elementid . "_'+currentRowNum+'_multi_day_'+nr);
							nr++;
						});
						
						// Reapply prettyCheckable JS 
						dayContainer.find('.use_prettycheckable').each(function() {
							var elem = jQuery(this);
							var lbl = elem.prev('label');
							var lbl_html = lbl.html();
							lbl.remove();
							elem.prettyCheckable({ label: lbl_html });
						});
					break;

					//change field to range of days dropdown
					case '2': 
						dayContainer.html('".$day_range_dropdown."');
						//update field attributes
						var elem= dayContainer.find('select.day_range_start').first();
						elem.attr('name', '" . $fieldname . "['+currentRowNum+']".'[day_range_start]'."');
						elem.attr('id', '" . $elementid . "_'+currentRowNum+'".'_day_range_start'."');
						elem.attr('data-uniqueRowNum', currentRowNum);				
						elem.val('".$selected_start."');					
						
						elem= dayContainer.find('select.day_range_end').first();
						elem.attr('name', '" . $fieldname . "['+currentRowNum+']".'[day_range_end]'."');
						elem.attr('id', '" . $elementid . "_'+currentRowNum+'".'_day_range_end'."');
						elem.attr('data-uniqueRowNum', currentRowNum);	
						elem.val('".$selected_end."');

					break;
				}
			});
			";		

		if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);

		// javascript for row and values
		$js .= "
			var uniqueRowNum".$field->id."	= ".count($values).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($values).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			";

		//if there is no set businesshours value, set the flag to true
		if ($dataEmpty)
			$js .= "dataEmpty".$field->id." = true";
		else 
			$js .= "dataEmpty".$field->id." = false;
			";

		$js .="
			/*
				Overview: adding new row of hour range at the end of the last existing range hour
				the function also handles re-ordering array indexes of existing range hours, 
				this is because when you delete and add repeatedly, the number could be out of order, 
				and if not re-ordered, may lead to index number conflict
			*/

			function addHourRow".$field->id."(el, groupval_box, fieldval_box, params)
			{	
				if (dataEmpty".$field->id.") return false; //exit function if currently there's no data

				//for sortable purposes later on
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				//find the lastfield and clone it
				var lastField = jQuery(el).closest('.hour-container').children('.hour-range-container').last();
				var newField  = lastField.clone();

				//find the business hours row number, for attributing the new field, and re-ordering of existing fields
				var currentRowNum = lastField.parent().attr('data-uniqueRowNum');

				//re-index array to handle messed up index number, to simplify, order the index number again every addition of new row.
				var groupHourRanges = jQuery(el).closest('.hour-container').children('.hour-range-container');
				var arrayIndex = 0;
				groupHourRanges.each(function() {
					var elem = jQuery(this).find('.hour_open').first();
					elem.attr('name', '" . $fieldname . "['+currentRowNum+'][hour_open]['+arrayIndex+']');
					elem.attr('id', '" . $elementid . "_'+currentRowNum+'_hour_open_'+arrayIndex);

					var elem = jQuery(this).find('.hour_close').first();
					elem.attr('name', '" . $fieldname . "['+currentRowNum+'][hour_close]['+arrayIndex+']');
					elem.attr('id', '" . $elementid . "_'+currentRowNum+'_hour_close_'+arrayIndex);
					arrayIndex++;
				});

				//FIND OUT HOW MANY ROWS OF hour-container already existed
				var hourRowCount =  lastField.parent().children('.hour-range-container').length;

				//update the newField's attributes based on already existed hour-container
				var elem= newField.find('select.hour_open').first();
				elem.attr('name', '" . $fieldname . "['+currentRowNum+'][hour_open]['+hourRowCount+']');
				elem.attr('id', '" . $elementid . "_'+currentRowNum+'_hour_open_'+hourRowCount);
				elem.attr('data-uniqueRowNum', currentRowNum);
				elem.val('0800');

				var elem= newField.find('select.hour_close').first();
				elem.attr('name', '" . $fieldname . "['+currentRowNum+'][hour_close]['+hourRowCount+']');
				elem.attr('id', '" . $elementid . "_'+currentRowNum+'_hour_close_'+hourRowCount);
				elem.attr('data-uniqueRowNum', currentRowNum);
				elem.val('1700');
			";

		// Add new hour to DOM
		$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery(el).closest('.hour-container') ) ;
				if (remove_previous) lastField.remove();
				";
		
		// Add new element to sortable objects (if field not in group)
		if (!$use_ingroup) $js .= "
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
				";
		
		// Show new field, increment counters
		$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
			}		

			//delete a single row of hour range
			function deleteHourRow".$field->id."(el, groupval_box, fieldval_box)
			{
				if (dataEmpty".$field->id.") return false; //exit function if currently there's no data

				// Find field value container
				var row = jQuery(el).closest('.hour-range-container');
				var hourRowCount =  jQuery(el).closest('.hour-container').children('.hour-range-container').length;

				// Add empty container if last element, instantly removing the given field value container
				if( hourRowCount == 1)
					addHourRow".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});

				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if(hourRowCount > 1) {
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ this.remove(); });
				}
			}	

			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				if (dataEmpty".$field->id.") {
					businesshoursReset".$field->id."();
					dataEmpty".$field->id." = false;
					return false; //exit function after enabling row
				}

				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();

				// Update the fields
				var elem= newField.find('div.day-container').first();
				elem.attr('data-uniqueRowNum', uniqueRowNum".$field->id.");

				//change the day type to dayrange
				elem.html('".$day_range_dropdown."');

				elem= newField.find('div.hour-container').first();
				elem.attr('data-uniqueRowNum', uniqueRowNum".$field->id.");

				//change the day type to dayrange
				elem.html('".$hourEntriesDefault."');

				//update field attributes
				setAttributeAndValues".$field->id."(newField.find('select.day_range_start').first(), {name: '" . $fieldname . "['+uniqueRowNum".$field->id."+']".'[day_range_start]'."', id:'" . $elementid . "_'+uniqueRowNum".$field->id."+'".'_day_range_start'."' , rowNum: uniqueRowNum".$field->id.", val:'mo'});
				setAttributeAndValues".$field->id."(newField.find('select.day_range_end').first(), {name: '" . $fieldname . "['+uniqueRowNum".$field->id."+']".'[day_range_end]'."', id:'" . $elementid . "_'+uniqueRowNum".$field->id."+'".'_day_range_end'."' , rowNum: uniqueRowNum".$field->id.", val:'fr'});
				setAttributeAndValues".$field->id."(newField.find('select.hour_open').first(), {name: '" . $fieldname . "['+uniqueRowNum".$field->id."+'][hour_open][0]', id:'" . $elementid . "_'+uniqueRowNum".$field->id."+'_hour_open_0' , rowNum: uniqueRowNum".$field->id."});
				setAttributeAndValues".$field->id."(newField.find('select.hour_close').first(), {name: '" . $fieldname . "['+uniqueRowNum".$field->id."+'][hour_close][0]', id:'" . $elementid . "_'+uniqueRowNum".$field->id."+'_hour_close_0' , rowNum: uniqueRowNum".$field->id."});

				// Remove HTML added by prettyCheckable JS, from the dupicate new INPUT SET
				var prettyContainers = newField.find('.prettyradio, .prettycheckbox');
				prettyContainers.find('input, label').each(function() {
					var el = jQuery(this);
					el.insertAfter(el.parent());
				});
				prettyContainers.remove();

				// Update INPUT SET container id
				newField.find('.fc_input_set').attr('id', '" . $elementid . "_'+uniqueRowNum".$field->id.");
				var js_class = ' use_prettycheckable day_radio ';
				
				// Update the new day radiogroup
				var theSet = newField.find('input:radio');
				var nr = 0;
				theSet.each(function() {
					setAttributeAndValues".$field->id."(jQuery(this), {name: '" . $fieldname . "['+uniqueRowNum".$field->id."+'][day_radio_options]', id: '" . $elementid . "_'+uniqueRowNum".$field->id."+'_day_radio_options'+nr, class: '" . $elementid . "_'+uniqueRowNum".$field->id." + js_class, elementGroupId: '" . $elementid . "_'+uniqueRowNum".$field->id.", for: '" . $elementid . "_'+uniqueRowNum".$field->id."+'_'+nr});
					jQuery(this).siblings('a').attr('class', '');
					jQuery(this).attr('checked', false);

					if(jQuery(this).val() == '2') {
						jQuery(this).siblings('a').attr('class', 'checked');
						jQuery(this).attr('checked', true);
					}
					nr++;
				});
				
				// Reapply prettyCheckable JS 
				newField.find('.use_prettycheckable').each(function() {
					var elem = jQuery(this);
					var lbl = elem.prev('label');
					var lbl_html = lbl.html();
					lbl.remove();
					elem.prettyCheckable({ label: lbl_html });
				});
				";

		// Add new field to DOM
		$js .= "lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				";
		
		// Add new element to sortable objects (if field not in group)
		if (!$use_ingroup) $js .= "
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
				";
		
		// Show new field, increment counters
		$js .="	if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				if (dataEmpty".$field->id.") {
					//delete pressed when data is empty, doing nothing');
					return false;
				}

				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');

				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1){
					//reset and disable the last businesshoursrow
					businesshoursReset".$field->id."();
					businesshoursDisable".$field->id."();
					businesshoursUpdateNameAndIds".$field->id."();
					dataEmpty".$field->id." = true;

					//intercept new rowCount make sure that after deletion of the last row, rowCount will stay 1 instead of 2
					if(rowCount".$field->id." == 2) {
						rowCount".$field->id."--;
					}
				}
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if(rowCount".$field->id." > 1) {
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();

					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ this.remove(); });
					rowCount".$field->id."--;
				}
			}
			";
		
		//immediately disable fields if data is empty
		if($dataEmpty) $js .= "
			jQuery(document).ready(function(){
				businesshoursDisable".$field->id."();
			});
			";
		
		$css .= $business_hours_css;
		
		$remove_button = '<span class="fcfield-delvalue fcfont-icon" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
		$move2 = '<span class="fcfield-drag-handle fcfont-icon" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
		$add_here = '';
		$add_here .= $add_position==2 || $add_position==3 ? '<span class="fcfield-insertvalue fc_before fcfont-icon" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
		$add_here .= $add_position==1 || $add_position==3 ? '<span class="fcfield-insertvalue fc_after fcfont-icon"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		$field->html = array();
		$n = 0;

		//if ($use_ingroup) {print_r($field->value);}
		foreach ($values as $value)
		{
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;

			//construct day radio options HTML
			$dayRadioOptions ='
				<span class="flexi label sub_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_DAY_GROUP_TYPE').'</span><br />
				<div id="radio1" class="nowrap_box">
					<input type="radio" id="'.$elementid_n.'_day_radio_option0" data-element-grpid="'.$elementid_n.'_day_radio_option" name="'.$fieldname_n.'[day_radio_options]'.'" class="use_prettycheckable day_radio_option0 day_radio" data-customClass="fcradiocheck" value ="0" '.(($value['day_radio_options']=='0')?' checked = "checked"':'').' />
					<label for="'.$elementid_n.'_day_radio_option0" class="fccheckradio_lbl hasTooltip" id="day_radio_option_label0" style="" title="">'.JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_DAY_GROUP_SINGLE').'</label>&nbsp; 
				</div>
				<div id="radio2" class="nowrap_box">
					<input type="radio" id="'.$elementid_n.'_day_radio_option1" data-element-grpid="'.$elementid_n.'_day_radio_option" name="'.$fieldname_n.'[day_radio_options]'.'" class="use_prettycheckable day_radio_option0 day_radio" data-customClass="fcradiocheck" value="1" '.(($value['day_radio_options']=='1')?' checked = "checked"':'').' />
					<label for="'.$elementid_n.'_day_radio_option1" class="fccheckradio_lbl hasTooltip" id="day_radio_option_label1" style="" title="">'.JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_DAY_GROUP_MULTIPLE').'</label>&nbsp; 
				</div>
				<div id="radio3" class="nowrap_box">
					<input type="radio" id="'.$elementid_n.'_day_radio_option2" data-element-grpid="'.$elementid_n.'_day_radio_option" name="'.$fieldname_n.'[day_radio_options]'.'" class="use_prettycheckable day_radio_option0 day_radio" data-customClass="fcradiocheck" value="2" '.(($value['day_radio_options']=='2')?' checked = "checked"':'').' />
					<label for="'.$elementid_n.'_day_radio_option2" class="fccheckradio_lbl hasTooltip" id="day_radio_option_label1" style="" title="">'.JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_DAY_GROUP_RANGE').'</label>&nbsp; 
				</div>';
				
			
			$dayEntries = '';
			//construct single day HTML
			if($value['day_radio_options']=='0'){
				$dayEntries = '<span class="flexi label sub_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SELECT_DAY').'</span><br />';
				$dayEntries .= JHTML::_('select.genericlist', $day_options, $fieldname_n.'[day_single]', ' class="day_range_end" data-uniqueRowNum="'.$n.'"', 'value', 'text', $value['days'], $elementid_n.'_end');
			}
			//construct multi day HTML
			if($value['day_radio_options']=='1'){
				$value['days'] = explode(',', $value['days']);
				$dayEntries = '
				<span class="flexi label sub_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SELECT_ONE_OR_MORE_DAYS').'</span><br />
				<fieldset '.$business_hours_checkbox_attributes.'>
					<div class="clearfix prettycheckbox labelright blue"><input class="use_prettycheckable" type="checkbox" name="'.$fieldname_n.'[multi_day][]'.'" id="'.$elementid_n.'_multi_day0" data-element-grpid="custom_businesshours_1" value="mo" '.(in_array("mo", $value['days'])?' checked = "checked"':'').'> <label for"'.$elementid_n.'_multi_day0">Monday</label><br></div>
					<div class="clearfix prettycheckbox labelright blue"><input class="use_prettycheckable" type="checkbox" name="'.$fieldname_n.'[multi_day][]'.'" id="'.$elementid_n.'_multi_day1" data-element-grpid="custom_businesshours_1" value="tu" '.(in_array("tu", $value['days'])?' checked = "checked"':'').'> <label for"'.$elementid_n.'_multi_day1">Tuesday</label><br></div>
					<div class="clearfix prettycheckbox labelright blue"><input class="use_prettycheckable" type="checkbox" name="'.$fieldname_n.'[multi_day][]'.'" id="'.$elementid_n.'_multi_day2" data-element-grpid="custom_businesshours_1" value="we" '.(in_array("we", $value['days'])?' checked = "checked"':'').'> <label for"'.$elementid_n.'_multi_day2">Wednesday</label><br></div>
					<div class="clearfix prettycheckbox labelright blue"><input class="use_prettycheckable" type="checkbox" name="'.$fieldname_n.'[multi_day][]'.'" id="'.$elementid_n.'_multi_day3" data-element-grpid="custom_businesshours_1" value="th" '.(in_array("th", $value['days'])?' checked = "checked"':'').'> <label for"'.$elementid_n.'_multi_day3">Thursday</label><br></div>
					<div class="clearfix prettycheckbox labelright blue"><input class="use_prettycheckable" type="checkbox" name="'.$fieldname_n.'[multi_day][]'.'" id="'.$elementid_n.'_multi_day4" data-element-grpid="custom_businesshours_1" value="fr" '.(in_array("fr", $value['days'])?' checked = "checked"':'').'> <label for"'.$elementid_n.'_multi_day4">Friday</label><br></div>
					<div class="clearfix prettycheckbox labelright blue"><input class="use_prettycheckable" type="checkbox" name="'.$fieldname_n.'[multi_day][]'.'" id="'.$elementid_n.'_multi_day5" data-element-grpid="custom_businesshours_1" value="sa" '.(in_array("sa", $value['days'])?' checked = "checked"':'').'> <label for"'.$elementid_n.'_multi_day5">Saturday</label><br></div>
					<div class="clearfix prettycheckbox labelright blue"><input class="use_prettycheckable" type="checkbox" name="'.$fieldname_n.'[multi_day][]'.'" id="'.$elementid_n.'_multi_day6" data-element-grpid="custom_businesshours_1" value="su" '.(in_array("su", $value['days'])?' checked = "checked"':'').'> <label for"'.$elementid_n.'_multi_day6">Sunday</label><br></div>
				</fieldset>
				';
			}
			//construct day ranges HTML
			if($value['day_radio_options']=='2'){
				$value['days'] = explode('-', $value['days']);
				$day_range_start = '<span class="flexi label sub_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SELECT_START_END_DAYS').'</span><br />'
					. JHTML::_('select.genericlist', $day_options, $fieldname_n.'[day_range_start]', ' class="day_range_start" data-uniqueRowNum="'.$n.'"', 'value', 'text', $value['days'][0], $elementid_n.'_start');
				$day_range_end = JHTML::_('select.genericlist', $day_options, $fieldname_n.'[day_range_end]', ' class="day_range_end" data-uniqueRowNum="'.$n.'"', 'value', 'text', $value['days'][1], $elementid_n.'_end');

				$dayEntries = $day_range_start. ' - ' .$day_range_end;

			}
			
			//construct hour range/s HTML
			$hourEntries = '';
			$value['hours'] = explode(',', $value['hours']);
			for ($i = 0; $i < count($value['hours']); $i++) {
				if($value['hours'][$i]) {
					$value['hours'][$i] = explode('-', $value['hours'][$i]);
				} else {
					$value['hours'][$i] = array('0800', '1700');
				}
				$hour_open = JHTML::_('select.genericlist', $hour_options, $fieldname_n.'[hour_open]['.$i.']', ' class="hour_open" data-uniqueRowNum="'.$n.'"', 'value', 'text', $value['hours'][$i][0], $elementid_n.'_hour_open_'.$i);
				$hour_close = JHTML::_('select.genericlist', $hour_options, $fieldname_n.'[hour_close]['.$i.']', ' class="hour_close" data-uniqueRowNum="'.$n.'"', 'value', 'text', $value['hours'][$i][1], $elementid_n.'_hour_close_'.$i);
				$hourEntries .= 
					'<div class="hour-range-container" style="float:left" data-hourRowNum="'.$i.'">'
						.$hour_open
						.$hour_close
						.'<span class="fcfield-delvalue fcfont-icon" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteHourRow'.$field->id.'(jQuery(this));"></span>'
					.'</div>';
			}

			$field->html[] = '
				<div class="business-hours-item">
					<div class="business-hours-radio-options">'
					. $dayRadioOptions
					.'</div>
					<div class="day-container" data-uniqueRowNum="'.$n.'">'
					. $dayEntries
					. '</div>
					<div class="hour-container" data-uniqueRowNum="'.$n.'">
					<span class="flexi label sub_label">'
					. JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SELECT_OPEN_CLOSE_HOURS')
					. '</span><br />'
					. $hourEntries
					. '<span class="fcfield-addvalue fcfont-icon hourRow" onclick="addHourRow'.$field->id.'(jQuery(this));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>
					</div>'
					.($use_ingroup ? '' : $move2)
					.($use_ingroup ? '' : $remove_button)
				.'</div>
				';				
		
			$n++;

			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}

		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue fcfont-icon" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$values = array();
		if(count($field->value)) {
			foreach($field->value as $value) {
				$value = unserialize($value);
				if(is_array($value)) $values[] = $value;
			}
		} else return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$view 		 = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));
		
		// Optional display
		$hide_minutes = (int) $field->parameters->get( 'hide_minutes', 3 ) ;
		$hours_format = (int) $field->parameters->get( 'hours_format', 3 ) ;
		$short_day 	= $field->parameters->get( 'short_day', 0 ) ;
		$remove_leading_zero = $field->parameters->get('remove_leading_zero', 0);
		$days_hours_separator = $field->parameters->get('days_hours_separator', ': ');
		$days_list_separator = $field->parameters->get('days_list_separator', ', ');
		$days_range_separator = $field->parameters->get('days_range_separator', ' - ');
		$hours_list_separator = $field->parameters->get('hours_list_separator', ', ');
		$hours_range_separator = $field->parameters->get('hours_range_separator', ' - ');
		$hours_minutes_separator = $field->parameters->get('hours_minutes_separator', ':');
		$hours_suffix = $field->parameters->get('hours_suffix', 'hr');
		$ampm_separator = $field->parameters->get('ampm_separator', '');

		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space   = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext        = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext       = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf     = $field->parameters->get( 'separatorf', 1 ) ;
		$opentag        = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag       = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}
		// initialise property
		$field->{$prop} = array();

		//Prep Display text for days 
		$dayText = array();
		
		//change text if param short_day is set
		if($short_day){
			$dayText['mo'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_MON');
			$dayText['tu'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_TUE');
			$dayText['we'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_WED');
			$dayText['th'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_THU');
			$dayText['fr'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_FRI');
			$dayText['sa'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SAT');
			$dayText['su'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SUN');
		}
		else {
			$dayText['mo'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_MONDAY');
			$dayText['tu'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_TUESDAY');
			$dayText['we'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_WEDNESDAY');
			$dayText['th'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_THURSDAY');
			$dayText['fr'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_FRIDAY');
			$dayText['sa'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SATURDAY');
			$dayText['su'] = JText::_('PLG_FLEXICONTENT_FIELDS_BUSINESSHOURS_SUNDAY');
		}

		foreach($values as $i => $value) {
			
			// prepare days string
			switch($value['day_radio_options']) {
				case 0:
					// single day
					$days = $dayText[$value['days']];
					break;
				case 1:
					// day list
					// convert comma-separated string to array
					$value['days'] = explode(',', $value['days']);
					// convert each value to language string
					foreach($value['days'] as $j => $dayval) $value['days'][$j] = $dayText[$dayval];
					// implode array using separator
					$days = implode($days_list_separator, $value['days']);
					break;
				case 2:
					// day range
					// break day range in two variables
					list($day_start, $day_end) = explode('-', $value['days']);
					$days = $dayText[$day_start] . $days_range_separator . $dayText[$day_end];
					break;
			}
			
			// prepare hours string
			// convert string into array of ranges
			$hours = explode(',', $value['hours']);
			// for each hours row, convert to open/close times
			foreach($hours as $ii => $hour) {
				// open and close hours
				$open_close = explode('-', $hour);
				
				// format open and close hours
				foreach($open_close as $iii => $open_close_hour) {
					
					$h = (int) substr($open_close_hour, 0, 2);
					$m = (string) substr($open_close_hour, 2, 2);
					
					if ($hours_format == 12) {
						
						$ampm = $h >= 12 ? 'PM' : 'AM';		// set am/pm string
						if ($h == 0) $h = 12;				// midnight changes from 0 to 12
						if ($h >= 13) $h -= 12;				// afternoon changes from 13-23 to 1-11
						
					}
					
					$open_close[$iii] = (!$remove_leading_zero && $h < 10 ? '0' : '')
						. $h 
						. ($hide_minutes && $m == 0 ? '' : $hours_minutes_separator . $m)
						. ($hours_format == 12 ? $ampm_separator . $ampm : $hours_suffix);
					
				}
				
				$hours[$ii] = (string) $open_close[0] . $hours_range_separator . (string) $open_close[1];
			}
			$hours = implode($hours_list_separator, $hours);
			
			$values[$i] = $pretext . '<span class="days">' . $days . '</span>' . $days_hours_separator . '<span class="hours">' . $hours . '</span>' . $posttext;
			
		}

		$field->{$prop}[] = $values;

		if (!$is_ingroup)// do not convert the array to string if field is in a group
		{
			// Apply separator and open/close tags
			if(count($field->{$prop})) {
				$field->{$prop}  = $opentag . implode($separatorf, $values) . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
	}
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item ){

		if ( !in_array($field->field_type, self::$field_types) ) return;

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;

		//create temp array
		$newpost = array();

		//convert the data format from arrays to delimited string
		$n = 0;
		foreach($post as $value){
			if(is_array($value)){
				if($value['day_radio_options'] >= 0 && $value['day_radio_options'] <= 2 && is_array($value['hour_open']) && is_array($value['hour_close'])){
					
					$days = '';
					switch($value['day_radio_options']) {
						// single day
						case 0:
							$days = $value['day_single'];
							break;
						
						// multiple days
						case 1:
							$days = implode(',', $value['multi_day']);
							break;
						
						// day range
						case 2:
							$days = $value['day_range_start'].'-'. $value['day_range_end'];
							break;
					}

					//convert range hours from array to delimited string
					$hours = array();
					$i = 0;
					foreach ($value['hour_open'] as $index => $hourValue) {
						$hours[$i] = $value['hour_open'][$index] . '-' . $value['hour_close'][$index];
						$i++;
					}
					if(count($hours)) $hours = implode(',', $hours);
					else $hours = '';

					//construct new array with the delimited strings
					if(!empty($days) && !empty($hours)) {
						$newpost[$n]['day_radio_options'] = $value['day_radio_options'];
						$newpost[$n]['days'] = $days;
						$newpost[$n]['hours'] = $hours;
						$n++;
					}
				}
			}
		}

		//if the data, return empty;
		if (!count($newpost)){
			$post='';
		}
		else{
			$post = $newpost;
		}
	}
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm') {
	}
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm') {
	}
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item) {
	}
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)	{
	}
	
}