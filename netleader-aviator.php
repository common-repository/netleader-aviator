<?php
if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
 * Plugin Name: NetLeader Aviator
 * Description: Aircraft Weight and Balance Calculator from NetLeader
 * Version: 1.1.4
 * Author: NetLeader
 * Plugin URI: https://www.netleader.com/nlaviator.html
 * Author URI: https://www.netleader.com
 * License: GPLv2
 */

/*  Copyright 2019  NetLeader  (email : support@netleader.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Hook for 'netleader_aviator' shortcode

function nlav_shortcode ( $atts ) { 

  // Example aircraft profile for adding new aircraft profiles
  $default_profile = "";
  $default_profile .= '{ "description" : "N172XX Cessna 172M Example", "items" : [';
  $default_profile .= '{ "item" : "Licensed Empty Weight", "weight" : 1364, "cg" : 37.9 },';
  $default_profile .= '{ "item" : "Oil (8 qts.)", "weight" : 15, "cg" : -13.3 },';
  $default_profile .= '{ "item" : "Fuel (Standard - 38 Gal, 228 lbs)", "weight" : 0, "cg" : 47.8 },';
  $default_profile .= '{ "item" : "Pilot and Front Seat Passenger", "weight" : 0, "cg" : 37.0 },';
  $default_profile .= '{ "item" : "Rear Passengers", "weight" : 0, "cg" : 72.9 },';
  $default_profile .= '{ "item" : "Baggage", "weight" : 0, "cg" : 92.3 }';
  $default_profile .= '], "cglimits" : [';
  $default_profile .= '{ "weight" : 1500, "fwdcg" : 35.0, "aftcg" : 47.3 },';
  $default_profile .= '{ "weight" : 1960, "fwdcg" : 35.0, "aftcg" : 47.3 },';
  $default_profile .= '{ "weight" : 2300, "fwdcg" : 38.5, "aftcg" : 47.3 }';
  $default_profile .= '] }';

  // Sql table name for use by netleader-aviator plugin
  global $wpdb;
  $table_name = $wpdb->prefix . 'nlaviator1';

  // Set current user's privilege  (Uses 1/0 instead of TRUE/FALSE for js embedding)
  if ( current_user_can ( 'edit_dashboard' )) {
    $can_edit = 1;
  } else {
    $can_edit = 0;
  }
  $show_edit = 0;
  $sql_id = 0;

  // Process 'DELETE' profile request
  if ( $_SERVER['REQUEST_METHOD'] === "POST" && $_POST['request'] === 'deleteprofile' ) {
    $is_delete = TRUE;
    if ( $can_edit === 1 ) {
      $acdata = cleanParams( $_POST['profjson'] );
      $acdesc = $acdata['description'];
      $where = array( 'description' => $acdesc );
      $desc_exists = $wpdb->get_row( "SELECT * FROM $table_name WHERE description='$acdesc'", ARRAY_A );
      if (  $desc_exists !== NULL ) {
        $wpdb->delete( $table_name, $where, $where_format = null );
      }
    }
  } else {
    $is_delete = FALSE;
  }

  // Process 'GET' profiles request
  if ( $_SERVER['REQUEST_METHOD'] === "GET" || $is_delete ) {
    $profiles = $wpdb->get_results( "SELECT id, description FROM $table_name ORDER BY description", ARRAY_A );
    $profiles_json = json_encode( $profiles );
    $retval = insert_header();
    $retval .= <<<_END
    <div id="acprofiles"></div>
    <script type="text/javascript">
      jQuery(document).ready (function ($) {
        // Create select profile buttons
        var profJSON = JSON.parse ( '$profiles_json' );
        tableHTML = "";
        for (var iCount = 0; iCount < profJSON.length; iCount++) {
          tableHTML += '<form method="post"><input type="hidden" name="request" value="getid">';
          tableHTML += '<input type="hidden" name="id" value="' + profJSON[iCount].id + '">';
          tableHTML += '<input class="nlavprof" type="submit" name="description" value="' + profJSON[iCount].description + '"></form>';
        }
        var canEdit = $can_edit;
        if ( canEdit === 1 ) {
          tableHTML += '<form method="post"><input type="hidden" name="request" value="add">';
          tableHTML += '<input class="nlavprof" type="submit" value="ADD NEW AIRCRAFT PROFILE"></form>';
          tableHTML += '<p></p><p></p>';
        } else if (iCount === 0) {
          tableHTML += '<form method="get" action="">';
          tableHTML += '<input class="nlavprof" type="submit" value="NO AIRCRAFT PROFILES AVAILABLE"></form>';
          tableHTML += '<p></p><p></p>';
        }
        $("#acprofiles").html (tableHTML );
      });
    </script>
_END;
    return $retval;
  }

  // Process 'POST' requests
  if ( $_SERVER['REQUEST_METHOD'] === "POST" ) {
    if ( $_POST['request'] === 'getid' || $_POST['request'] === 'editprofile' ) {
      $sql_id = intval( $_POST['id'] );
      if ( $sql_id > 0 ) {
        $acdata = $wpdb->get_results( "SELECT * FROM $table_name WHERE id=$sql_id", ARRAY_A );
        $acjson = $acdata[0]['nlavjson'];
      } else {
        return '<p>ERROR: Unable to find aircraft profile $sql_id</p>';
      }
      if ( $_POST['request'] === 'getid' && $can_edit === 1 ) {
        $can_edit = 0;
        $show_edit = 1;
      }
    } elseif ( $_POST['request'] === 'add' ) {
      $acjson = $default_profile;
    } elseif ( $_POST['request'] === 'updateprofile' && $can_edit === 1 ) {
      $acdata = cleanParams( $_POST['profjson'] );
      $acjson = json_encode( $acdata );
      $acdesc = $acdata['description'];
      $table_data = array( 'type' => 'PROFILE', 'dbversion' => '1.0', 'description' => $acdesc, 'nlavjson' => $acjson );
      $where = array( 'description' => $acdesc );
      $desc_exists = $wpdb->get_row( "SELECT * FROM $table_name WHERE description='$acdesc'", ARRAY_A );
      if (  $desc_exists === NULL ) {
        $wpdb->insert( $table_name, $table_data );
      } else {
        $wpdb->update( $table_name, $table_data, $where );
      }
      $updated = $wpdb->get_results( "SELECT * FROM $table_name WHERE description='$acdesc'", ARRAY_A );
      $can_edit = 0;
      $show_edit = 1;
      $sql_id = $updated[0]['id'];
    } elseif ( $_POST['request'] === 'help' ) {
      $nlav_help_path = pathinfo( __FILE__, PATHINFO_DIRNAME ) . '/includes/html/';
      $nlav_help_filename = $nlav_help_path . 'nlavguide.html';
      $retval = file_get_contents ( $nlav_help_filename );
      $nlav_url = plugins_url( '', __FILE__ );
      $retval = str_replace( '$nlav_url', $nlav_url, $retval );
      return $retval;
    }
  }

// Prepare HTML and javascript response for POST requests
$retval = insert_header();
$retval .= <<<_END
  <div id="acdescription"></div>
  <div id="statusmsg"></div>
  <div id="acsummary"></div>
  <div id="acenvelope"></div>
  <div id="acitems"></div>
  <div id="accglimits"></div>
  <div id="nlavupprofile"></div>

  <script type="text/javascript">
    
    // "Document Ready" event handler
    jQuery(document).ready (function ($) {
      var acParams = JSON.parse( '$acjson' );
      var maxWeight = 0;
      var canEdit = $can_edit;
      var showEdit = $show_edit;
      var modProfile = false;
      var sqlID = $sql_id;
      var tableHTML = "";

      if ( canEdit ) {
        inpEdit = " ";
      } else {
        inpEdit = " readonly ";
      }

      function createDescTable() {
        tableHTML =  '<table id="desctable" class="nlavdesctab nlavctr">';
        tableHTML += '<tr><td class="nlavdesctab"><input class="nlavdesc"' + inpEdit + 'type="text" size="72" value="' + acParams.description + '"></td>';
        tableHTML += '</tr></table>';
        $("#acdescription").html (tableHTML);
      }
      createDescTable();

      function createStatusMsg() {
        tableHTML = '<p class="nlaverror">COMPUTATION IN PROGRESS, RESULTS NOT VALID</p>';
        $("#statusmsg").html( tableHTML );
        }
      createStatusMsg();
      
      function createSummaryTable() {
        tableHTML =  '<table id="summarytable" class="nlavctr"><tr>';
        tableHTML += '<th class="nlavnumhdr">Max Gross</th>';
        tableHTML += '<th class="nlavnumhdr">TOTAL WEIGHT</th>';
        tableHTML += '<th class="nlavnumhdr">Over/Under Max</th>';
        tableHTML += '<th class="nlavnumhdr">Zero Fuel Weight</th>';
        tableHTML += '</tr><tr>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="---"></td>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="---"></td>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="---"></td>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="---"></td>';
        tableHTML += '</tr><tr>';
        tableHTML += '<th class="nlavnumhdr nlavnarrow">Fwd CG Limit</th>';
        tableHTML += '<th class="nlavnumhdr nlavnarrow">TOTAL CG</th>';
        tableHTML += '<th class="nlavnumhdr nlavnarrow">Aft CG Limit</th>';
        tableHTML += '<th class="nlavnumhdr nlavnarrow">Zero Fuel CG</th>';
        tableHTML += '</tr><tr>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="---"></td>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="---"></td>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="---"></td>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="---"></td>';
        tableHTML += '</tr></table>';
        $("#acsummary").html (tableHTML);
      }
      createSummaryTable();
      
      function createCGEnvSVG() {
        // CG Envelope SVG Constants
        var svg_width = "400";
        var svg_height = "400";
        var total_CG_icon_size = "10";
        var zero_fuel_icon_string = "M 5 0 L 10 5 5 10 0 5 5 0";
        var stroke_width_icon = "3";
        var svg_large_font_size = "20";
        var svg_small_font_size = "15";
        var stroke_width_cg_env = "2";
        var cg_text_pos = 'x="200" y="390"';
        var fwd_text_pos = 'x="60" y="370"';
        var aft_text_pos = 'x="320" y="370"';
        var weight_text_pos = 'x="40" y="200"';
        var weight_text_pos_2 = '40,200';
        var total_text_icon_pos = 'x="60" y="10"';
        var total_text_pos = 'x="80" y="20"';
        var zero_fuel_text_icon_pos = 'x="260" y="10"';
        var zero_fuel_text_pos = 'x="280" y="20"';
        var total_CG_pos = 'x="268" y="45"';
        var zero_fuel_pos = 'x="256" y="131"';
        var cg_line_string = 'x1="273" y1="50" x2="261" y2="136"';
        // Create CG Envelope SVG
        tableHTML =  '<div><h5 class="nlavenvhdr">CG Envelope</h5></div>';
        tableHTML += '<table id="cgsvgtable" class="nlavctr"><tr>';
        tableHTML += '<td class="cgsvgtd">';
        tableHTML += '<svg id="cgsvgenv" width="' + svg_width + '" height="' + svg_height + '" xmlns="http://www.w3.org/2000/svg">';
        tableHTML += '<defs>';
        tableHTML += '<pattern id="smallGrid" width="10" height="10" patternUnits="userSpaceOnUse">';
        tableHTML += '<path d="M 10 0 L 0 0 0 10" fill="none" stroke="gray" stroke-width="0.5"/>';
        tableHTML += '</pattern>';
        tableHTML += '<pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse">';
        tableHTML += '<rect width="100" height="100" fill="url(#smallGrid)"/>';
        tableHTML += '<path d="M 100 0 L 0 0 0 100" fill="none" stroke="gray" stroke-width="1"/>';
        tableHTML += '</pattern>';
        tableHTML += '<g id="totalCG">';
        tableHTML += '<rect width="' + total_CG_icon_size + '" height="' + total_CG_icon_size + '" style="fill:none; stroke-width:' + stroke_width_icon + '; stroke:green;" />';
        tableHTML += '</g><g id="zeroFuel">';
        tableHTML += '<path d="' + zero_fuel_icon_string + '" fill="none" stroke="blue" stroke-width="' + stroke_width_icon + '" />';
        tableHTML += '</g></defs>';
        tableHTML += '<rect width="100%" height="100%" fill="url(#grid)" />';
        tableHTML += '<polygon id="cgpolygon" points="" style="fill:none;stroke:black;stroke-width:' + stroke_width_cg_env + '" />';
        tableHTML += '<text ' + cg_text_pos + ' text-anchor="middle" fill="black" font-family="Verdana" font-size="' + svg_large_font_size + '">CG</text>';
        tableHTML += '<text ' + fwd_text_pos + ' fill="black" font-family="Verdana" font-size="' + svg_small_font_size + '">Forward</text>';
        tableHTML += '<text ' + aft_text_pos + ' fill="black" font-family="Verdana" font-size="' + svg_small_font_size + '">Aft</text>';
        tableHTML += '<text ' + weight_text_pos + ' fill="black" font-family="Verdana" font-size="' + svg_large_font_size + '" transform="rotate(270 ' + weight_text_pos_2 + ')">Weight</text>';
        tableHTML += '<use  xlink:href="#totalCG" ' + total_text_icon_pos + ' />';
        tableHTML += '<text ' + total_text_pos + ' fill="black" font-family="Verdana" font-size="15">Total</text>';
        tableHTML += '<use  xlink:href="#zeroFuel" ' + zero_fuel_text_icon_pos + ' />';
        tableHTML += '<text ' + zero_fuel_text_pos + ' fill="black" font-family="Verdana" font-size="15">Zero Fuel</text>';
        tableHTML += '<!-- Draw Total, Zero Fuel points and line-->';
        tableHTML += '<use id="cgIcon" class="svgcgvis" xlink:href="#totalCG" ' + total_CG_pos + ' />';
        tableHTML += '<use id="zfIcon" class="svgzfvis" xlink:href="#zeroFuel" ' + zero_fuel_pos + ' />';
        tableHTML += '<line id="cgLine" class="svgzfvis" ' + cg_line_string + ' style="stroke:gray;"/>';
        tableHTML += '</svg></td></tr></table>';
        $("#acenvelope").html (tableHTML);
      }
      createCGEnvSVG();

      function createItemsTable() {
        // Create loading table
        tableHTML = '<div><h5 class="nlavenvhdr">Loading Table</h5></div>';
        tableHTML += '<table id="itemstable" class="nlavctr"><tr>';
        tableHTML += '<th class="nlavtxthdr">Item</th>';
        tableHTML += '<th class="nlavnumhdr">Weight (lbs)</th>';
        tableHTML += '<th class="nlavnumhdr">Arm (in)</th>';
        tableHTML += '<th class="nlavnumhdr">Moment (lbs-in/1000)</th></tr>';
        for (var iCount in acParams.items) {
          var aWeight = Number(acParams.items[iCount].weight);
          var wtEdit = inpEdit;
          var wtBorder = "";
          if ( !canEdit && aWeight === 0 ) {
            wtEdit = " ";
            wtBorder = " " + "nlavblu";
          }
          tableHTML += '<tr>';
          tableHTML += '<td class="nlavtxt nlavwide"><input class="nlavtxt"' + inpEdit + 'type="text" size="30" value="' + acParams.items[iCount].item + '"></td>';
          tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum' + wtBorder + '"' + wtEdit + 'type="text" size="10" value="' + aWeight.toFixed(0) + '"></td>';
          tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum"' + inpEdit + 'type="text" size="10" value="' + Number(acParams.items[iCount].cg).toFixed(1) + '"></td>';
          tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="---"></td>';
          tableHTML += '</tr>';
        }
        tableHTML += '</table>';
        tableHTML += '<table id="itemstotal" class="nlavctr"><tr>';
        tableHTML += '<td class="nlavtxt nlavwide"><input class="nlavtxt" readonly type="text" size="30" value="TOTAL"></td>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="Loading.."></td>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="Loading.."></td>';
        tableHTML += '<td class="nlavnum nlavnarrow"><input class="nlavnum" readonly type="text" size="10" value="Loading.."></td>';
        tableHTML += '</tr></table>';
        if ( canEdit === 1 ) {
          tableHTML += '<table id="moditems" class="nlavctr"><tr><td class="adddelbuttons">';
          tableHTML += '<button type="button" class="nlavbutton" id="additemrow">Add Row</button>';
          tableHTML += '<button type="button" class="nlavbutton" id="delitemrow">Delete Row</button>';
          tableHTML += '</td></tr></table';
        }
        $("#acitems").html (tableHTML);
      }
      createItemsTable();

      function createCGEnvTable() {
        if ( canEdit === 1 ) {
          tableHTML = '<div><h5 class="nlavenvhdr">CG Envelope Table</h5></div>';
          tableHTML += '<table id="cglimits" class="nlavctr"><tr>';
          tableHTML += '<th class="nlavnumhdr">Weight</th>';
          tableHTML += '<th class="nlavnumhdr">Fwd CG Limit</th>';
          tableHTML += '<th class="nlavnumhdr">Aft CG Limit</th></tr>';
          for (var iCount in acParams.cglimits ) {
            tableHTML += '<tr><td class="nlavnum nlavwide"><input class="nlavnum"' + inpEdit + 'type="text" size="10" value="';
            tableHTML += Number(acParams.cglimits[iCount].weight).toFixed(0);
            tableHTML += '"></td><td class="nlavnum nlavwide"><input class="nlavnum"' + inpEdit + 'type="text" size="10" value="';
            tableHTML += Number(acParams.cglimits[iCount].fwdcg).toFixed(1);
            tableHTML += '"></td><td class="nlavnum nlavwide"><input class="nlavnum"' + inpEdit + 'type="text" size="10" value="';
            tableHTML += Number(acParams.cglimits[iCount].aftcg).toFixed(1);
            tableHTML += '"></td></tr>';
          }
          tableHTML += '</table>';
          tableHTML += '<table id="modcgenv" class="nlavctr"><tr><td class="adddelbuttons">';
          tableHTML += '<button type="button" class="nlavbutton" id="addcgrow">Add Row</button>';
          tableHTML += '<button type="button" class="nlavbutton" id="delcgrow">Delete Row</button>';
          tableHTML += '</td></tr></table';
        }
        $("#accglimits").html (tableHTML);
      }
      if ( canEdit === 1 ) {
        createCGEnvTable();
      }

      function createUpDelProfile() {
        // Create 'Update Profile' and 'Delete Profile' buttons or 'Edit Profile' button
        if ( canEdit === 1 ) {
          tableHTML  = '<table id="updatedel" class="nlavctr"><tr><td class="updelbutton">';
          tableHTML += '<form method="post">';
          tableHTML += '<input type="hidden" name="request" value="updateprofile">';
          tableHTML += '<input type="hidden" name="profjson" id="upprofdata" value="None">';
          tableHTML += '<input class="nlavupprof" type="submit" id="upprofile" value="Update Profile">';
          tableHTML += '</form></td><td class="updelbutton">';
          tableHTML += '<form method="post">';
          tableHTML += '<input type="hidden" name="request" value="deleteprofile">';
          tableHTML += '<input type="hidden" name="profjson" id="delprofdata" value="None">';
          tableHTML += '<input class="nlavupprof" type="submit" id="delprofile" value="Delete Profile">';
          tableHTML += '</form></td></tr>';
          tableHTML += '</table>';
          $("#nlavupprofile").append (tableHTML);
        } else if ( showEdit === 1 ) {
          tableHTML  = '<table id="editprof" class="nlavctr"><tr><td class="updelbutton">';
          tableHTML += '<form method="post">';
          tableHTML += '<input type="hidden" name="request" value="editprofile">';
          tableHTML += '<input type="hidden" name="id" value="' + sqlID + '">';
          tableHTML += '<input class="nlavupprof" type="submit" id="edprofile" value="Edit Profile">';
          tableHTML += '</form></td></tr>';
          tableHTML += '</table>';
          $("#nlavupprofile").append (tableHTML);
        }
      }
      createUpDelProfile();
      
      // Update Description on change
      $(document).on('change', '#desctable', function() {
        if ( canEdit === 1 ) modProfile = true;
        var atable = $('#desctable')[0];
        var newDesc = atable.rows[0].cells[0].children[0].value;
        newDesc = cleanStr( newDesc.trim() );
        if ( newDesc.length > 0 && canEdit === 1 ) {
          acParams.description = newDesc;
        }
        atable.rows[0].cells[0].children[0].value = acParams.description;
        computeCG();
      });

      // Update the Loading / Items Table on change
      $(document).on('change', '#itemstable', function() {
        if ( canEdit === 1 ) modProfile = true;
        var atable = $('#itemstable')[0];
        var atableRows = atable.rows.length;
        var itemIndx = 0;
        var wtIndx = 1;
        var cgIndx = 2;
        for (var i=1; i < atableRows; i++) {
          var newItem = atable.rows[i].cells[itemIndx].children[0].value;
          if ( canEdit === 1 ) {
            acParams.items[i-1].item = cleanStr(newItem.trim());
          }
          atable.rows[i].cells[itemIndx].children[0].value = acParams.items[i-1].item;
          
          var newWeight = atable.rows[i].cells[wtIndx].children[0].value;
          if ( (! isNaN(newWeight)) && newWeight >= 0 ) {
            acParams.items[i-1].weight = Number(newWeight).toFixed(0);
          }
          atable.rows[i].cells[wtIndx].children[0].value = acParams.items[i-1].weight;

          var newCG = atable.rows[i].cells[cgIndx].children[0].value;
          if ( (! isNaN(newCG)) && canEdit === 1 ) {
            acParams.items[i-1].cg = Number(newCG).toFixed(1);
          }
          atable.rows[i].cells[cgIndx].children[0].value = acParams.items[i-1].cg;

        }
        computeCG();
      });

      // Add row to Loading / Items Table on click
      $(document).on('click', '#additemrow', function() {
        if ( confirm ( 'Add new row to Items Table?' ) && canEdit === 1 ) {
          modProfile = true;
          acParams.items.push( {"item" : "New Item", "weight" : 0, "cg" : 0 } );
        }
        createItemsTable();
        computeCG();
      });

      // Delete row from Loading / Items Table on click
      $(document).on('click', '#delitemrow', function() {
        if ( acParams.items.length > 1 && canEdit === 1 ) {
          if ( confirm ( 'Delete last row from Items Table?' ) ) {
            modProfile = true;
            acParams.items.pop();
          }
        } else {
          alert ( "Can't delete last row from Items Table" );
        }
        createItemsTable();
        computeCG();
      });

      // Update the CG Envelope Tables on change
      $(document).on('change', '#cglimits', function() {
        if ( canEdit === 1 ) modProfile = true;
        var atable = $('#cglimits')[0];
        var atableRows = atable.rows.length;
        var wtIndx = 0;
        var cgfwdIndx = 1;
        var cgaftIndx = 2;
        for (var i=1; i < atableRows; i++) {

          var newWeight = atable.rows[i].cells[wtIndx].children[0].value;
          if ( (! isNaN(newWeight)) && newWeight >= 0 && canEdit === 1 ) {
            acParams.cglimits[i-1].weight = Number(newWeight).toFixed(0);
          }
          atable.rows[i].cells[wtIndx].children[0].value = acParams.cglimits[i-1].weight;

          var newFwdcg = atable.rows[i].cells[cgfwdIndx].children[0].value;
          if ( (! isNaN(newFwdcg)) && canEdit === 1 ) {
            acParams.cglimits[i-1].fwdcg = Number(newFwdcg).toFixed(1);
          }
          atable.rows[i].cells[cgfwdIndx].children[0].value = acParams.cglimits[i-1].fwdcg;

          var newAftcg = atable.rows[i].cells[cgaftIndx].children[0].value;
          if ( (! isNaN(newAftcg)) && newAftcg >= acParams.cglimits[i-1].fwdcg && canEdit === 1) {
            acParams.cglimits[i-1].aftcg = Number(newAftcg).toFixed(1);
          }
          atable.rows[i].cells[cgaftIndx].children[0].value = acParams.cglimits[i-1].aftcg;
        }
        computeCG();
      });

      // Add row to CG Envelope Table on click
      $(document).on('click', '#addcgrow', function() {
        if ( confirm ( 'Add new row to CG Envelope Table?' ) && canEdit === 1 ) {
          modProfile = true;
          acParams.cglimits.push( {"weight" : 0, "fwdcg" : 0, "aftcg" : 0 } );
        }
        createCGEnvTable();
        computeCG();
      });

      // Delete row from CG Envelope Table on click
      $(document).on('click', '#delcgrow', function() {
        if ( acParams.cglimits.length > 1 && canEdit === 1 ) {
          if ( confirm ( 'Delete last row from CG Envelope Table?' ) ) {
            modProfile = true;
            acParams.cglimits.pop();
          }
        } else {
          alert ( "Can't delete last row from CG Envelope Table" );
        }
        createCGEnvTable();
        computeCG();
      });

      // Convert tables to JSON for posting on click: 'Update Profile'
      $(document).on('click', '#upprofile', function() {
        var newJSON = "";
        newJSON = JSON.stringify(acParams);
        $("#upprofdata").val(newJSON);
        if ( ! confirm( 'Update Profile for: ' + acParams['description'] )) {
          event.preventDefault();
        } else {
          modProfile = false;
        }
      });

      // Convert tables to JSON for posting on click: 'Delete Profile'
      $(document).on('click', '#delprofile', function() {
        var newJSON = ""
        newJSON = JSON.stringify(acParams);
        $("#delprofdata").val(newJSON);
        if ( ! confirm( 'Delete Profile for: ' + acParams['description'] )) {
          event.preventDefault();
        }
      });

      // Ask if OK to exit without updating profile
      $(document).on('click', '#backbutton', function() {
        if ( modProfile ) {
          if ( ! confirm( 'Exit without updating profile?' )) {
            event.preventDefault();
          }
        }
      });

      // Compute CG
      function computeCG() {
        
        // Initialize CG Envelope SVG
        $("#cgpolygon").css( "visibility", "hidden" );
        $(".svgcgvis").css( "visibility", "hidden" );
        $(".svgzfvis").css( "visibility", "hidden" );

        // Initialize Summary Table & Status Msg
        createSummaryTable();

        // Compute Moments, Weights, CGs from itemstable
        var atable = $('#itemstable')[0];
        var atableRows = atable.rows.length;
        var itemMom = 0;
        var momIndx = 3;
        var totalWeight = 0;
        var totalCG = 0;
        var totalMom = 0;
        var zeroFuelCG = 0;
        var zeroFuelWt = 0;
        var fuelWt = 0;
        var fuelCG = 0;
        var fuelArm = 0;
        for (var i=1; i < atableRows; i++) {
          itemMom = acParams.items[i-1].weight * acParams.items[i-1].cg;
          atable.rows[i].cells[momIndx].children[0].value = (Number(itemMom)/1000).toFixed(1);
          totalWeight += Number(acParams.items[i-1].weight);
          totalMom += Number(itemMom);
          var itemStr = acParams.items[i-1].item;
          if ( /fuel/i.test( itemStr )) {
            var aWeight = acParams.items[i-1].weight;
            var aCG = acParams.items[i-1].cg;
            fuelWt += aWeight;
            fuelArm += aWeight * aCG;
          }
        }
        if ( fuelWt !== 0 ) {
          fuelCG = fuelArm / fuelWt;
        }
        // Update Aircraft Summary Table and Items Total Table
        if ( totalWeight != 0 ) {
          totalCG = totalMom / totalWeight;
        } else {
          totalCG = 0;
        }
        // Calculate Zero Fuel Weight, CG
        zeroFuelWt = totalWeight - fuelWt;
        if ( fuelWt === 0 || zeroFuelWt === 0 ) {
          var noZero = true;
          zeroFuelCG = zeroFuelWt = 0;
        } else {
          var noZero = false;
          zeroFuelCG = (totalMom - (fuelWt * fuelCG)) / zeroFuelWt;
        }
        var atable = $('#summarytable')[0];
        atable.rows[1].cells[1].children[0].value = Number( totalWeight ).toFixed(0);
        atable.rows[3].cells[1].children[0].value = Number( totalCG ).toFixed(1);
        var atable = $('#itemstotal')[0];
        atable.rows[0].cells[0].children[0].value = 'TOTAL';
        atable.rows[0].cells[1].children[0].value = Number( totalWeight ).toFixed(0);
        atable.rows[0].cells[2].children[0].value = Number( totalCG ).toFixed(1);
        atable.rows[0].cells[3].children[0].value = (Number( totalMom )/1000).toFixed(1);
        
        // Validate CG Envelope table
        var limitsEntries = 0;
        var badLimits = false;
        var prevEntry = 0;
        for ( var i = 0; i < acParams.cglimits.length; i++ ) {
          if ( acParams.cglimits[i].weight != 0 ) {
            limitsEntries += 1;
            if ( prevEntry >= acParams.cglimits[i].weight ) {
              badLimits = true;
              break;
            }
            prevEntry = acParams.cglimits[i].weight;
            if ( acParams.cglimits[i].aftcg <= acParams.cglimits[i].fwdcg ) {
              badLimits = true;
              break;
            }
          } else {
            break;
          }
        }
        if ( limitsEntries < 2 ) {
          badLimits = true;
        }
        if ( badLimits ) {
          $("#statusmsg").html ( '<p class="nlaverror">INVALID CG ENVELOPE</p>' );
        } else {
          // Draw CG Limits envelope
          var lowCG = highCG = diffcg = 0;
          var minx = maxx = diffx = 0;
          var lowWt = highWt = diffwt = 0;
          var miny = maxy = diffy = 0;

          lowWt = highWt = acParams.cglimits[0].weight;
          lowCG = highCG = acParams.cglimits[0].fwdcg;
          for ( var i = 0; i < limitsEntries; i++ ) {
            if ( acParams.cglimits[i].weight < lowWt ) lowWt = acParams.cglimits[i].weight;
            if ( acParams.cglimits[i].fwdcg < lowCG ) lowCG = acParams.cglimits[i].fwdcg;
            if ( acParams.cglimits[i].aftcg < lowCG ) lowCG = acParams.cglimits[i].aftcg;
            if ( acParams.cglimits[i].weight > highWt ) highWt = acParams.cglimits[i].weight;
            if ( acParams.cglimits[i].fwdcg > highCG ) highCG = acParams.cglimits[i].fwdcg;
            if ( acParams.cglimits[i].aftcg > highCG ) highCG = acParams.cglimits[i].aftcg;
          }
          diffcg = highCG - lowCG;
          diffwt = highWt - lowWt;
          minx = miny = 50;
          maxx = maxy = 350;
          diffx = maxx - minx;
          diffy = maxy - miny;

          var envPoints = "";
          for ( var i = 0; i < limitsEntries; i++ ) {
            var ptWt = acParams.cglimits[i].weight;
            var ptCg = acParams.cglimits[i].fwdcg;
            var x = ((( ptCg - lowCG ) / diffcg ) * diffx ) + minx;
            x = Number(x).toFixed(0);
            var y = maxy - ((( ptWt - lowWt ) / diffwt ) * diffy );
            y = Number(y).toFixed(0);
            envPoints += ( x + ',' + y );
            envPoints += " ";
          }
          for ( var i = (limitsEntries - 1); i > -1; i-- ) {
            var ptWt = acParams.cglimits[i].weight;
            var ptCg = acParams.cglimits[i].aftcg;
            var x = ((( ptCg - lowCG ) / diffcg ) * diffx ) + minx;
            x = Number(x).toFixed(0);
            var y = maxy - ((( ptWt - lowWt ) / diffwt ) * diffy );
            y = Number(y).toFixed(0);
            envPoints += ( x + ',' + y );
            envPoints += " ";
          }
          envPoints = envPoints.trim();
          $("#cgpolygon").css( "visibility", "visible" );
          $('#cgpolygon').attr( "points", envPoints );

          // Display Max Weight, Loaded Weight and Useful Load
          var atable = $('#summarytable')[0];
          maxWeight = acParams.cglimits[ limitsEntries - 1 ].weight;
          atable.rows[1].cells[0].children[0].value = Number( maxWeight ).toFixed(0);
          atable.rows[1].cells[2].children[0].value = Number( totalWeight - maxWeight ).toFixed(0);
          // Determine if loaded weight is in range
          var badWeight = false;
          if ( totalWeight < acParams.cglimits[0].weight ) {
            badWeight = true;
          }
          if ( totalWeight > acParams.cglimits[limitsEntries - 1].weight ) {
            badWeight = true;
          }
          if ( badWeight ) {
            $("#statusmsg").html ( '<p class="nlaverror">LOADED WEIGHT OUT OF RANGE</p>' );
          } else {
            // Compute CG limits at totalWeight
            var x1fwd = x1aft = x2fwd = x2aft = y1 = y2 = m = b = 0;
            // Determine two weights within range of loaded weight
            for ( var i = 1; i < limitsEntries; i++) {
              x1fwd = acParams.cglimits[i-1].fwdcg;
              x1aft = acParams.cglimits[i-1].aftcg;
              y1 = acParams.cglimits[i-1].weight;
              x2fwd = acParams.cglimits[i].fwdcg;
              x2aft = acParams.cglimits[i].aftcg;
              y2 = acParams.cglimits[i].weight;
              if ( totalWeight <= y2 ) {
                break;
              }
            }
            // Line y = mx + b;  x is CG, y is weight
            if ( x2fwd - x1fwd != 0) {
              m = ( y2 - y1 ) / ( x2fwd - x1fwd );
              b = y1 - ( m * x1fwd );
              fwdLimit = ( totalWeight - b ) / m;
            } else {
              fwdLimit = x1fwd;
            }
            
            if ( x2aft - x1aft != 0) {
              m = ( y2 - y1 ) / ( x2aft - x1aft );
              b = y1 - ( m * x1aft );
              aftLimit = ( totalWeight - b ) / m;
            } else {
              aftLimit = x1aft;
            }
            // Display fwd and aft CG limits
            var atable = $('#summarytable')[0];
            atable.rows[3].cells[0].children[0].value = Number(fwdLimit).toFixed(1);
            atable.rows[3].cells[2].children[0].value = Number(aftLimit).toFixed(1);
            // Determine if CG is within range
            var badCG = false;
            if ( totalCG < fwdLimit || totalCG > aftLimit ) {
              badCG = true;
              $("#statusmsg").html ( '<p class="nlaverror">CG OUT OF RANGE</p>' );
            } else {
              $("#statusmsg").html ( '<p class="nlavgood">AIRCRAFT LOADED WITHIN WEIGHT AND CG LIMITS</p>' );
              // Plot CG and Zero Fuel icons 
              var cgPosX1 = ((( totalCG - lowCG ) / diffcg ) * diffx ) + minx;
              var cgPosX1 = Number(cgPosX1).toFixed(0);
              var cgIconX = Number(cgPosX1 - 5).toFixed(0);
              var cgPosY1 = maxy - ((( totalWeight - lowWt ) / diffwt ) * diffy );
              var cgPosY1 = Number(cgPosY1).toFixed(0);
              var cgIconY = Number(cgPosY1 - 5).toFixed(0);
              
              $(".svgcgvis").css( "visibility", "visible" );
              $('#cgIcon').attr( { "x": cgIconX, "y": cgIconY } );

              if ( ! noZero ) {
                if ( zeroFuelWt < lowWt ) {
                  $("#statusmsg").html ( '<p class="nlaverror">ZERO FUEL WEIGHT OUT OF RANGE</p>' );
                } else {
                  var zfPosX2 = ((( zeroFuelCG - lowCG ) / diffcg ) * diffx ) + minx;
                  var zfPosX2 = Number(zfPosX2).toFixed(0);
                  var zfIconX = Number(zfPosX2 - 5).toFixed(0);
                  var zfPosY2 = maxy - ((( zeroFuelWt - lowWt ) / diffwt ) * diffy );
                  var zfPosY2 = Number(zfPosY2).toFixed(0);
                  var zfIconY = Number(zfPosY2 - 5).toFixed(0);
                  $(".svgzfvis").css( "visibility", "visible" );
                  $('#zfIcon').attr( { "x": zfIconX, "y": zfIconY } );
                  $('#cgLine').attr( { "x1": cgPosX1, "y1": cgPosY1, "x2": zfPosX2, "y2": zfPosY2 } );
                  var atable = $('#summarytable')[0];
                  atable.rows[1].cells[3].children[0].value = Number( zeroFuelWt ).toFixed(0);
                  atable.rows[3].cells[3].children[0].value = Number( zeroFuelCG ).toFixed(1);
                }
              }
            }
          }
        }
        return;
      }

      // Remove all special characters
      function cleanStr(aStr) {
        return aStr.replace(/[^a-zA-Z0-9&-\/\(\)\.\,;: ]/g, "");
      }

      // Force computation of CG before we go
      computeCG();
    });


  </script>        
_END;

  return $retval;
}


// Print utility for logging to a file in the plugin directory
function netleader_log_print ( $netleader_print_str ) {
  $netleader_print_filename = pathinfo( __FILE__, PATHINFO_DIRNAME ) . '/netleader-log.txt';
  file_put_contents ( $netleader_print_filename, time() . ": " . $netleader_print_str . "\r\n", FILE_APPEND | LOCK_EX );
}

// Build HTML for NLAV header
function insert_header() {
  $hdr_html = "";
  $hdr_html .= '<div class="nlavheader">';
  $hdr_html .= '<form action="" method="get">';
  $hdr_html .= '<input type="submit" class="nlavsmbtn" value="Back">';
  $hdr_html .= '</form>';
  $hdr_html .= '<form method="post">';
  $hdr_html .= '<input type="hidden" name="request" value="help">';
  $hdr_html .= '<input type="submit" class="nlavsmbtn" value="Help">';
  $hdr_html .= '</form>';
  $hdr_html .= '<h4 class="nlavtitle">NetLeader Aviator Weight and Balance Calculator</h4>';
  $hdr_html .= '<h6 class="nlavtitle">FOR EDUCATIONAL PURPOSES ONLY</h6>';
  $hdr_html .= '</div>';
  return $hdr_html;
}

// Build parameters object from JSON, sanitizing input along the way
function cleanParams ( $raw_json ) {
  $new_params = array();
  $raw_json = str_replace( '\\', '', $raw_json );
  $raw_params = json_decode( $raw_json, TRUE );
  
  if (json_last_error() === JSON_ERROR_NONE) {
    $new_params['description'] = sanitize_text_field( $raw_params['description'] );
    for ( $i=0; $i < sizeof( $raw_params['items']); $i++ ) {
      $new_item = sanitize_text_field( $raw_params['items'][$i]['item'] );
      $new_weight = intval( $raw_params['items'][$i]['weight'] );
      $new_cg = number_format( $raw_params['items'][$i]['cg'], 1 );
      $new_params['items'][$i] = array( "item" => $new_item, "weight" => $new_weight, "cg" => $new_cg );
    }
    for ( $i=0; $i < sizeof( $raw_params['cglimits']); $i++ ) {
      $new_weight = intval( $raw_params['cglimits'][$i]['weight'] );
      $new_fwd_cg = number_format( $raw_params['cglimits'][$i]['fwdcg'], 1 );
      $new_aft_cg = number_format( $raw_params['cglimits'][$i]['aftcg'], 1 );
      $new_params['cglimits'][$i] = array( "weight" => $new_weight, "fwdcg" => $new_fwd_cg, "aftcg" => $new_aft_cg );
    }
  }
  return $new_params;
}

// Register and Enqueue CSS style sheet
function nlav_plugin_styles() {
  $no_deps = array();
  wp_register_style( 'nlavstyles', plugins_url( 'css/nlavstyles.css', __FILE__ ), $no_deps, NULL );
  wp_enqueue_style( 'nlavstyles' );
}

// Activation hook: Create data table
function nlav_activate() {
	global $wpdb;
  
  if ( ! current_user_can( 'activate_plugins' ) ) {
    return;
  }
  
  $table_name = $wpdb->prefix . 'nlaviator1';

  $charset_collate = $wpdb->get_charset_collate();

  if ( $wpdb->get_var('SHOW TABLES LIKE ' . $table_name) != $table_name) {
    $sql = "CREATE TABLE $table_name (
      id INTEGER(10) UNSIGNED AUTO_INCREMENT,
      type VARCHAR(255),
      dbversion VARCHAR(255),
      description VARCHAR(255),
      nlavjson TEXT,
      PRIMARY KEY  (ID)
      ) $charset_collate;";

	  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	  dbDelta( $sql );

    }
}

// Deactivation hook
function nlav_deactivate () {

  if ( ! current_user_can( 'activate_plugins' ) ) {
    return;
  }
  // Nothing to do at the moment
}

// Uninstall hook
function nlav_uninstall () {
  // drop table
  if ( ! current_user_can('activate_plugins') ) {
    return;
  }
  global $wpdb;
  $table_name = $wpdb->prefix . 'nlaviator1';
  $sql = "DROP TABLE IF EXISTS $table_name;";
  $wpdb->query( $sql );
}


// ADD HOOKS TO WORDPRESS

// Add hook for 'netleader_aviator' shortcode
add_shortcode ( 'netleader_aviator', 'nlav_shortcode' );
// Add hook for CSS stylesheet
add_action ( 'wp_enqueue_scripts', 'nlav_plugin_styles' );
// Add hook for activation
register_activation_hook ( __FILE__, 'nlav_activate' );
// Add hook for deactivation
register_deactivation_hook ( __FILE__, 'nlav_deactivate' );
// Add hook for uninstall
// NB: ABSOLUTELY NOTHING AFTER LAST CHARACTER OF LAST STATEMENT
register_uninstall_hook( __FILE__, 'nlav_uninstall' );
