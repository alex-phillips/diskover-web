<?php
/*
Copyright (C) Chris Park 2017
diskover is released under the Apache 2.0 license. See
LICENSE for the full license text.
 */

require '../vendor/autoload.php';
use diskover\Constants;
error_reporting(E_ALL ^ E_NOTICE);
require "../src/diskover/Auth.php";
require "../src/diskover/Diskover.php";
require "d3_inc.php";
require "vars_inc.php";


// Get search results from Elasticsearch for thread usage
$results = [];
$searchParams = [];

// Setup search query
$searchParams['index'] = $esIndex;
$searchParams['type']  = 'file';

$thread_usage = [];

# show up to 40 threads in chart
for ($i=0; $i < 40; $i++) {
    // Execute the search
    $searchParams['body'] = [
     'size' => 0,
     'query' => [
       'match' => [
         'indexing_thread' => $i
       ]
     ]
    ];

    // Send search query to Elasticsearch
    $queryResponse = $client->search($searchParams);
    $thread_usage[$i] = [ 'label' => $i, 'count' => $queryResponse['hits']['total'] ];
}
$js_threads = json_encode($thread_usage);


// Get search results from Elasticsearch for tags
$results = [];
$searchParams = [];

$totalFilesize = ['untagged' => 0, 'delete' => 0, 'archive' => 0, 'keep' => 0];
$totalFilesizeAll = 0;

// Setup search query
$searchParams['index'] = $esIndex;
$searchParams['type']  = 'file';

// Execute the search
foreach ($totalFilesize as $tag => $value) {
    if ($tag === "untagged") { $t = ""; } else { $t = $tag; }
    $searchParams['body'] = [
     'size' => 0,
     'query' => [
       'match' => [
         'tag' => $t
       ]
     ],
      'aggs' => [
        'total_size' => [
          'sum' => [
            'field' => 'filesize'
          ]
        ]
      ]
    ];

    // Send search query to Elasticsearch
    $queryResponse = $client->search($searchParams);

    // Get total size of all files with tag
    $totalFilesize[$tag] = $queryResponse['aggregations']['total_size']['value'];
    $totalFilesizeAll += $totalFilesize[$tag];
}

$results = [];
$searchParams = [];
$tagCounts = ['untagged' => 0, 'delete' => 0, 'archive' => 0, 'keep' => 0];

// Setup search query
$searchParams['index'] = $esIndex;
$searchParams['type']  = 'file,directory';

// Execute the search
foreach ($tagCounts as $tag => $value) {
    if ($tag === "untagged") { $t = ""; } else { $t = $tag; }
    $searchParams['body'] = [
       'size' => 0,
       'query' => [
         'match' => [
           'tag' => $t
         ]
       ]
    ];

    // Send search query to Elasticsearch
    $queryResponse = $client->search($searchParams);

    // Get total for tag
    $tagCounts[$tag] = $queryResponse['hits']['total'];
}

// Get search results from Elasticsearch for duplicate files
$results = [];
$searchParams = [];
$totalDupes = 0;
$totalFilesizeDupes = 0;

// Setup search query
$searchParams['index'] = $esIndex;
$searchParams['type']  = 'file';


// Setup search query for dupes count
$searchParams['body'] = [
   'size' => 0,
    'aggs' => [
      'total_size' => [
        'sum' => [
          'field' => 'filesize'
        ]
      ]
    ],
    'query' => [
      'query_string' => [
        'query' => 'dupe_md5:(NOT "")',
        'analyze_wildcard' => 'true'
      ]
    ]
];
$queryResponse = $client->search($searchParams);

// Get total count of duplicate files
$totalDupes = $queryResponse['hits']['total'];

// Get total size of all duplicate files
$totalFilesizeDupes = $queryResponse['aggregations']['total_size']['value'];


// Get search results from Elasticsearch for index stats
$results = [];
$searchParams = [];

// Setup search query
$searchParams['index'] = $esIndex;
$searchParams['type']  = 'crawlstat';

$searchParams['body'] = [
    '_source' => ['indexing_date'],
    'size' => 1,
    'query' => [
            'match' => [
                'event' => 'start'
            ]
     ],
     'sort' => [
         'indexing_date' => [
             'order' => 'asc'
         ]
     ]
];
$queryResponse = $client->search($searchParams);

$firstcrawlstarttime = $queryResponse['hits']['hits'][0]['_source']['indexing_date'];

$searchParams['body'] = [
    '_source' => ['indexing_date'],
    'size' => 1,
    'query' => [
            'match' => [
                'event' => 'start'
            ]
     ],
     'sort' => [
         'indexing_date' => [
             'order' => 'desc'
         ]
     ]
];
$queryResponse = $client->search($searchParams);

$lastcrawlstarttime = $queryResponse['hits']['hits'][0]['_source']['indexing_date'];

$searchParams['body'] = [
    '_source' => ['indexing_date'],
    'size' => 1,
    'query' => [
            'match' => [
                'event' => 'stop'
            ]
     ],
     'sort' => [
         'indexing_date' => [
             'order' => 'desc'
         ]
     ]
];
$queryResponse = $client->search($searchParams);

$lastcrawlstoptime = $queryResponse['hits']['hits'][0]['_source']['indexing_date'];

$searchParams['body'] = [
   'size' => 0,
    'aggs' => [
      'total_elapsed' => [
        'sum' => [
          'field' => 'elapsed_time'
        ]
      ]
    ],
    'query' => [
            'match_all' => (object) []
     ]
];
$queryResponse = $client->search($searchParams);

// Get total elapsed time (in seconds) of crawl(s)
$crawlelapsedtime = $queryResponse['aggregations']['total_elapsed']['value'];

// determine if crawl is finished by seeing if last crawlstarttime is newer than last crawlstoptime
$crawlfinished = ($lastcrawlstarttime < $lastcrawlstoptime) ? true : false;


// Get search results from Elasticsearch for number of files
$results = [];
$searchParams = [];

// Setup search query
$searchParams['index'] = $esIndex;
$searchParams['type']  = "file";

$searchParams['body'] = [
    'size' => 0,
    'query' => [
        'match_all' => (object) []
     ]
];
$queryResponse = $client->search($searchParams);

// Get total count of files
$totalfiles = $queryResponse['hits']['total'];


// Get search results from Elasticsearch for number of directories
$results = [];
$searchParams = [];

// Setup search query
$searchParams['index'] = $esIndex;
$searchParams['type']  = "directory";

$searchParams['body'] = [
    'size' => 0,
    'query' => [
        'match_all' => (object) []
     ]
];
$queryResponse = $client->search($searchParams);

// Get total count of directories
$totaldirs = $queryResponse['hits']['total'];


// Get search results from Elasticsearch for disk space info
$results = [];
$searchParams = [];

// Setup search query
$searchParams['index'] = $esIndex;
$searchParams['type']  = "diskspace";

$searchParams['body'] = [
    'size' => 1,
    'query' => [
        'match_all' => (object) []
     ]
];
$queryResponse = $client->search($searchParams);

// Get disk space info from queryResponse
$diskspace_path = $queryResponse['hits']['hits'][0]['_source']['path'];
$diskspace_total = $queryResponse['hits']['hits'][0]['_source']['total'];
$diskspace_free = $queryResponse['hits']['hits'][0]['_source']['free'];
$diskspace_available = $queryResponse['hits']['hits'][0]['_source']['available'];
$diskspace_used = $queryResponse['hits']['hits'][0]['_source']['used'];
$diskspace_date = $queryResponse['hits']['hits'][0]['_source']['indexing_date'];

// store disk space path into session var
$_SESSION['rootpath'] = $diskspace_path;

// update path cookie
if ($diskspace_path !== getCookie('path')) {
    createCookie('path', $diskspace_path);
}

if ($esIndex2 != "") {
    // Get search results from Elasticsearch for disk space info from index2
    $results = [];
    $searchParams = [];

    // Setup search query
    $searchParams['index'] = $esIndex2;
    $searchParams['type']  = "diskspace";

    $searchParams['body'] = [
        'size' => 1,
        'query' => [
            'match_all' => (object) []
         ]
    ];
    $queryResponse = $client->search($searchParams);

    // Get disk space info from queryResponse
    $diskspace2_path = $queryResponse['hits']['hits'][0]['_source']['path'];
    $diskspace2_total = $queryResponse['hits']['hits'][0]['_source']['total'];
    $diskspace2_free = $queryResponse['hits']['hits'][0]['_source']['free'];
    $diskspace2_available = $queryResponse['hits']['hits'][0]['_source']['available'];
    $diskspace2_used = $queryResponse['hits']['hits'][0]['_source']['used'];
    $diskspace2_date = $queryResponse['hits']['hits'][0]['_source']['indexing_date'];
}


// Get recommended delete size/count
$recommended_delete_size = 0;
$recommended_delete_count = 0;

$results = [];
$searchParams = [];

// Setup search query
$searchParams['index'] = $esIndex;
$searchParams['type']  = "file";

// Setup search query for dupes count
$searchParams['body'] = [
   'size' => 0,
    'aggs' => [
      'total_size' => [
        'sum' => [
          'field' => 'filesize'
        ]
      ]
    ],
    'query' => [
      'query_string' => [
        'query' => 'last_modified:{* TO now-6M} AND last_access:{* TO now-6M}'
      ]
    ]
];
$queryResponse = $client->search($searchParams);

// Get total count of recommended files to remove
$recommended_delete_count = $queryResponse['hits']['total'];

// Get total size of allrecommended files to remove
$recommended_delete_size = $queryResponse['aggregations']['total_size']['value'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>diskover &mdash; Dashboard</title>
	<link rel="stylesheet" href="css/bootswatch.min.css" media="screen" />
  <link rel="stylesheet" href="css/diskover.css" media="screen" />
	<style>
        .darken {
            color: gray !important;
        }
        .darken a {
            color: gray !important;
        }
        .darken a:hover {
            color: gray !important;
        }
		.arc text {
			font: 10px sans-serif;
			text-anchor: middle;
		}
		.arc path {
			stroke: #0B0C0E;
		}
        #diskspacechart rect {
            fill: #BD1B00;
            stroke: black;
        }
        #diskspacechart text {
            font-size: 10px;
            fill: white;
            font-weight: bold;
        }
        #diskspacechart {
            height: 22px;
            width: 400px;
            border:1px solid #000;
            background-color: #7EB26D;
        }
        #diskspacechart-indexed rect {
            fill: #DA722C;
            stroke: black;
        }
        #diskspacechart-indexed text {
            font-size: 8px;
            fill: white;
        }
        #diskspacechart-indexed {
            height: 18px;
            width: 400px;
            border:1px solid #000;
            background-color: #282C34;
            margin-bottom: 10px;
        }
        .axis {
	        font: 10px sans-serif;
            fill: #ccc;
	    }
	    .axis path,
	    .axis line {
    	  fill: none;
    	  stroke: #000;
    	  shape-rendering: crispEdges;
        }
        #threadchart rect {
            stroke: black;
        }
	</style>
</head>
<body>
<?php include "nav.php"; ?>
<div class="container-fluid" style="margin-top:70px;">
  <div class="row">
    <div class="col-xs-6">
      <div class="well">
        <h1><i class="glyphicon glyphicon-piggy-bank"></i> Space Savings</h1>
        <p>You could save <span style="font-size:24px;font-weight:bold;color:#D20915;"><?php echo formatBytes($totalFilesizeAll); ?></span> of disk space if you delete or archive all your files.<br />
            diskover found <span style="font-size:16px;font-weight:bold;color:#D20915;"><?php echo $recommended_delete_count ?></span> (<span style="font-size:16px;font-weight:bold;color:#D20915;"><?php echo formatBytes($recommended_delete_size) ?></span>) <a href="advanced.php?index=<?php echo $esIndex ?>&amp;index2=<?php echo $esIndex2 ?>&amp;submitted=true&amp;p=1&amp;last_mod_time_high=now-6M&amp;last_access_time_high=now-6M&amp;doctype=file">recommended files</a> to remove. <span style="font-size:10px;color:#555;">(>6M mtime & atime)</span></p>
        <p><i class="glyphicon glyphicon-file" style="color:#738291;size:13px;font-weight:bold;"></i> Files: <span style="font-weight:bold;color:#D20915;"><?php echo $totalfiles; ?></span> &nbsp;&nbsp; <i class="glyphicon glyphicon-folder-close" style="color:skyblue;size:13px;font-weight:bold;"></i> Directories: <span style="font-weight:bold;color:#D20915;"><?php echo $totaldirs; ?></span> &nbsp;&nbsp;
            <i class="glyphicon glyphicon-duplicate" style="color:#738291;size:13px;font-weight:bold;"></i> Dupes: <span style="font-weight:bold;color:#D20915;"><?php echo $totalDupes; ?></span> (<span style="font-weight:bold;color:#D20915;"><?php echo formatBytes($totalFilesizeDupes); ?></span>)</p>
      </div>
      <div class="alert alert-dismissible alert-success">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <strong><i class="glyphicon glyphicon-home"></i> Welcome to diskover-web!</strong> Please support diskover on <a target="_blank" href="https://www.patreon.com/diskover">Patreon</a> or <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CLF223XAS4W72" target="_blank">PayPal</a>.
      </div>
      <?php
      if ($totalDupes === 0) {
      ?>
      <div class="alert alert-dismissible alert-info">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h4><i class="glyphicon glyphicon-duplicate"></i> No dupe files found.</h4>
        <p>Run diskover with the --finddupes flag after crawl finishes to check for duplicate files.</p>
      </div>
      <?php
      }
      ?>
      <?php
      if ($totalDupes > 0) {
      ?>
      <div class="alert alert-dismissible alert-warning">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h4><i class="glyphicon glyphicon-duplicate"></i> Duplicate files!</h4>
        <p>It looks like you have <a href="simple.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;q=dupe_md5:(NOT &quot;&quot;)&amp;doctype=file" class="alert-link">duplicate files</a>, tag the copies for deletion to save space.</p>
      </div>
      <?php
      }
      ?>
      <?php
      if ($tagCounts['untagged'] > 0) {
      ?>
      <div class="alert alert-dismissible alert-info">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h4><i class="glyphicon glyphicon-tags"></i> Untagged files!</h4>
        <p>It looks like you have <a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;tag=&quot;&quot;" class="alert-link">untagged files</a>, time to start tagging and free up some space :)</p>
      </div>
      <?php
      }
      ?>
      <?php
      if ($tagCounts['untagged'] == 0 AND $totalFilesize['delete'] > 0 AND $totalFilesize['archive'] > 0 AND $totalFilesize['keep'] > 0 ) {
      ?>
      <div class="alert alert-dismissible alert-info">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="glyphicon glyphicon-thumbs-up"></i> <strong>Good job!</strong> It looks like all files have been tagged.
      </div>
      <?php
      }
      ?>
      <div class="well">
          <h4 style="display: inline;"><i class="glyphicon glyphicon-dashboard"></i> Index Crawl Stats</h4>&nbsp;&nbsp;&nbsp;&nbsp;<small>Index: <span class="text-success"><strong><?php echo $esIndex; ?></strong></span></small>
              <p><i class="glyphicon glyphicon-calendar"></i> Started at: <span class="text-success"><?php echo $firstcrawlstarttime; ?></span> UTC.<br />
              <?php if ($crawlfinished) { ?>
                  <i class="glyphicon glyphicon-flag"></i> Finished at: <span class="text-success"><?php echo $lastcrawlstoptime; ?></span> UTC.<br />
                  <i class="glyphicon glyphicon-time"></i> Total crawl time: <span class="text-success"><?php echo secondsToTime($crawlelapsedtime); ?></span></p>
                  <?php } else { ?>
                  <strong><i class="glyphicon glyphicon-tasks text-danger"></i> Crawl is still running. <a href="dashboard.php?<?php echo $_SERVER['QUERY_STRING']; ?>">Reload</a> to see updated results.</strong><small> (Last updated: <?php echo (new \DateTime())->format('Y-m-d\TH:i:s T'); ?>)</small></p>
              <?php } ?>
              <p><small><span style="color:#555"><i class="glyphicon glyphicon-info-sign"></i> If running parallel crawls, start time is first crawl and finish time is last crawl, the total crawl time is cumulative.</span></small></p>
      </div>
      <div class="panel panel-primary">
      <div class="panel-heading">
          <h3 class="panel-title"><i class="glyphicon glyphicon-tasks"></i> Crawl Thread Usage</h3>
      </div>
  <div class="panel-body">
      <div id="threadchart" class="text-center"></div>
      <div>
          <?php foreach($thread_usage as $key => $value) { if ($value['count'] > 0) { ?>
        <span class="label" style="background-color: black;">thread-<?php echo $key; ?> <?php echo $value['count']; ?></span>
    <?php } } ?>
    </div>
  </div>
  </div>
    </div>
    <div class="col-xs-6">
        <div class="well">
          <h4><i class="glyphicon glyphicon-hdd"></i> Disk Space Overview</h4>
          <p>Path: <span class="text-success"><strong><?php echo $diskspace_path; ?></strong></span></p>
          <div id="diskspacechart"></div>
          <div id="diskspacechart-indexed"></div>
          <?php
          if ($esIndex2 != "") {
              $diskspace_used_change = number_format(changePercent($diskspace_used, $diskspace2_used), 2);
              $diskspace_free_change = number_format(changePercent($diskspace_free, $diskspace2_free), 2);
              $diskspace_available_change = number_format(changePercent($diskspace_available, $diskspace2_available), 2);
          }
          ?>
          <p>Total: <span style="font-weight:bold;color:#D20915;"><?php echo formatBytes($diskspace_total); ?></span>&nbsp;&nbsp;&nbsp;&nbsp;
              Used: <span style="font-weight:bold;color:#D20915;"><?php echo formatBytes($diskspace_used); ?></span> <?php if ($esIndex2 != "") { ?><small><span style="color:gray;"><?php echo formatBytes($diskspace2_used); ?></span> <span style="color:<?php echo $diskspace_used_change > 0 ? "red" : "#29FE2F"; ?>;">(<?php echo $diskspace_used_change > 0 ? '<i class="glyphicon glyphicon-chevron-up"></i> +' : '<i class="glyphicon glyphicon-chevron-down"></i>'; ?><?php echo $diskspace_used_change;  ?>%)</span></small><?php } ?><br />
              Free: <span style="font-weight:bold;color:#D20915;"><?php echo formatBytes($diskspace_free); ?></span> <?php if ($esIndex2 != "") { ?><small><span style="color:gray;"><?php echo formatBytes($diskspace2_free); ?></span> <span style="color:<?php echo $diskspace_free_change > 0 ? "#29FE2F" : "red"; ?>;">(<?php echo $diskspace_free_change > 0 ? '<i class="glyphicon glyphicon-chevron-up"></i> +' : '<i class="glyphicon glyphicon-chevron-down"></i>'; ?><?php echo $diskspace_free_change; ?>%)</span></small><?php } ?>&nbsp;&nbsp;&nbsp;&nbsp;
              Available: <span style="font-weight:bold;color:#D20915;"><?php echo formatBytes($diskspace_available); ?></span> <?php if ($esIndex2 != "") { ?><small><span style="color:gray;"><?php echo formatBytes($diskspace2_available); ?></span> <span style="color:<?php echo $diskspace_available_change > 0 ? "#29FE2F" : "red"; ?>;">(<?php echo $diskspace_available_change > 0 ? '<i class="glyphicon glyphicon-chevron-up"></i> +' : '<i class="glyphicon glyphicon-chevron-down"></i>'; ?><?php echo $diskspace_available_change; ?>%)</span></small><?php } ?></p>
        </div>
        <div class="panel panel-primary chartbox">
            <div class="panel-heading">
                <h3 style="display: inline;" class="panel-title"><i class="glyphicon glyphicon-scale"></i> Top 10 Largest Files</h3><small>&nbsp;&nbsp;&nbsp;&nbsp;<a href="top50.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;path=<?php echo $path; ?>">Top 50</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="top50.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;path=<?php echo $path; ?>">Directories</a></small>
            </div>
            <div class="panel-body">
            <table class="table table-striped table-hover table-condensed" style="font-size:12px;">
              <thead>
                <tr>
                  <th class="text-nowrap">#</th>
                  <th class="text-nowrap">Name</th>
                  <th class="text-nowrap">File Size</th>
                  <th class="text-nowrap">Modified (utc)</th>
                  <th class="text-nowrap">Path</th>
              </tr>
            </thead>
            <tbody>
                  <?php
                  // Get search results from Elasticsearch for top 10 largest files
                  $results = [];
                  $searchParams = [];

                  // Setup search query
                  $searchParams['index'] = $esIndex;
                  $searchParams['type']  = 'file';


                  // Setup search query for largest files
                  $searchParams['body'] = [
                      'size' => 10,
                      '_source' => ['filename', 'path_parent', 'filesize', 'last_modified'],
                      'query' => [
                          'match_all' => (object) []
                      ],
                      'sort' => [
                          'filesize' => [
                              'order' => 'desc'
                          ]
                      ]
                  ];
                  $queryResponse = $client->search($searchParams);

                  $largestfiles = $queryResponse['hits']['hits'];
                  $n = 1;
                  foreach ($largestfiles as $key => $value) {
                    ?>
                    <tr><td class="darken"><?php echo $n; ?></td>
                        <td class="path"><a href="view.php?id=<?php echo $value['_id'] . '&amp;index=' . $value['_index'] . '&amp;doctype=file'; ?>"><?php echo $value['_source']['filename']; ?></a></td>
                        <td class="text-nowrap darken"><span style="font-weight:bold;color:#D20915;"><?php echo formatBytes($value['_source']['filesize']); ?></span></td>
                        <td class="text-nowrap darken"><?php echo $value['_source']['last_modified']; ?></td>
                        <td class="path darken"><a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;path_parent=<?php echo $value['_source']['path_parent']; ?>&amp;doctype=file"><?php echo $value['_source']['path_parent']; ?></a></td>
                    </tr>
                  <?php $n++; }
                   ?>
               </tbody>
          </table>
        </div>
        </div>
        <div class="row">
          <div class="col-xs-6">
            <div class="panel panel-primary chartbox">
            <div class="panel-heading">
                <h3 class="panel-title" style="display:inline;"><i class="glyphicon glyphicon-tag"></i> Tag Counts</h3><small>&nbsp;&nbsp;&nbsp;&nbsp;<a href="tags.php?<?php echo $_SERVER['QUERY_STRING']; ?>">View all</a></small>
            </div>
            <div class="panel-body">
                <div id="tagcountchart" class="text-center"></div>
                <div class="chartbox">
                  <span class="label" style="background-color:#666666;"><a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;tag=">untagged <?php echo $tagCounts['untagged']; ?></a></span>
                  <span class="label" style="background-color:#F69327"><a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;tag=delete">delete <?php echo $tagCounts['delete']; ?></a></span>
                  <span class="label" style="background-color:#65C165"><a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;tag=archive">archive <?php echo $tagCounts['archive']; ?></a></span>
                  <span class="label" style="background-color:#52A3BB"><a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;tag=keep">keep <?php echo $tagCounts['keep']; ?></a></span>
              </div>
            </div>
            </div>
          </div>
        	<div class="col-xs-6">
                <div class="panel panel-primary chartbox">
                <div class="panel-heading">
                    <h3 class="panel-title" style="display:inline;"><i class="glyphicon glyphicon-hdd"></i> Total File Sizes</h3><small>&nbsp;&nbsp;&nbsp;&nbsp;<a href="tags.php?<?php echo $_SERVER['QUERY_STRING']; ?>">View all</a></small>
                </div>
            <div class="panel-body">
                <div id="filesizechart" class="text-center"></div>
                <div class="chartbox">
                  <span class="label" style="background-color:#666666;"><a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;tag=">untagged <?php echo formatBytes($totalFilesize['untagged']); ?></a></span>
                  <span class="label" style="background-color:#F69327"><a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;tag=delete">delete <?php echo formatBytes($totalFilesize['delete']); ?></a></span>
                  <span class="label" style="background-color:#65C165"><a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;tag=archive">archive <?php echo formatBytes($totalFilesize['archive']); ?></a></span>
                  <span class="label" style="background-color:#52A3BB"><a href="advanced.php?<?php echo $_SERVER['QUERY_STRING']; ?>&amp;submitted=true&amp;p=1&amp;tag=keep">keep <?php echo formatBytes($totalFilesize['keep']); ?></a></span>
              </div>
            </div>
        	</div>
        </div>
        </div>

      </div>
  </div>
</div>
<script language="javascript" src="js/jquery.min.js"></script>
<script language="javascript" src="js/bootstrap.min.js"></script>
<script language="javascript" src="js/diskover.js"></script>
<script language="javascript" src="js/d3.v3.min.js"></script>
<!-- d3 charts -->
    <script>
        var margin = {top: 20, right: 20, bottom: 30, left: 70},
        width = 600 - margin.left - margin.right,
        height = 250 - margin.top - margin.bottom;

        var color = d3.scale.category20c();

        var x = d3.scale.ordinal().rangeRoundBands([0, width], .05);

        var y = d3.scale.linear().range([height, 0]);

        var xAxis = d3.svg.axis()
            .scale(x)
            .orient("bottom");

        var yAxis = d3.svg.axis()
            .scale(y)
            .orient("left")
            .ticks(10);

        var svg = d3.select("#threadchart").append("svg")
            .attr("width", width + margin.left + margin.right)
            .attr("height", height + margin.top + margin.bottom)
          .append("g")
            .attr("transform",
                  "translate(" + margin.left + "," + margin.top + ")");

        var data = <?php echo $js_threads ?>;

        data.forEach(function(d) {
            d.label = d.label;
            d.value = d.count;
        });

        x.domain(data.map(function(d) { return (d.value > 0) ? d.label : ''; }));
        y.domain([0, d3.max(data, function(d) { return d.value; })]);

        svg.append("g")
          .attr("class", "x axis")
          .attr("transform", "translate(0," + height + ")")
          .call(xAxis)
        .selectAll("text")
          .style("text-anchor", "end")
          .attr("dx", "-.8em")
          .attr("dy", "-.55em")
          .attr("transform", "rotate(-90)" );

        svg.append("g")
          .attr("class", "y axis")
          .call(yAxis)
        .append("text")
          .attr("transform", "rotate(-90)")
          .attr("y", 6)
          .attr("dy", ".71em")
          .style("text-anchor", "end")
          .text("Queue items");

        svg.selectAll("bar")
          .data(data)
        .enter().append("rect")
          .style("fill", color)
          .attr("x", function(d) { return x(d.label); })
          .attr("width", x.rangeBand())
          .attr("y", function(d) { return y(d.value); })
          .attr("height", function(d) { return height - y(d.value); });
    </script>
	<script>
		var count_untagged = <?php echo $tagCounts['untagged'] ?>;
		var count_delete = <?php echo $tagCounts['delete'] ?>;
		var count_archive = <?php echo $tagCounts['archive'] ?>;
		var count_keep = <?php echo $tagCounts['keep'] ?>;

		var dataset = [{
			label: 'untagged',
			count: count_untagged
		}, {
			label: 'delete',
			count: count_delete
		}, {
			label: 'archive',
			count: count_archive
		}, {
			label: 'keep',
			count: count_keep
		}];

		var width = 200;
		var height = 200;
		var radius = Math.min(width, height) / 2;

		var color = d3.scale.ordinal()
			.range(["#666666", "#F69327", "#65C165", "#52A3BB"]);

		var svg = d3.select("#tagcountchart")
			.append('svg')
			.attr('width', width)
			.attr('height', height)
			.append('g')
			.attr('transform', 'translate(' + width / 2 + ',' + height / 2 + ')');

		var pie = d3.layout.pie()
			.value(function(d) {
				return d.count;
			})
			.sort(null);

		var path = d3.svg.arc()
			.outerRadius(radius - 10)
			.innerRadius(0);

		var label = d3.svg.arc()
			.outerRadius(radius - 40)
			.innerRadius(radius - 40);

		var arc = svg.selectAll('.arc')
			.data(pie(dataset))
			.enter().append('g')
			.attr('class', 'arc');

		arc.append('path')
			.attr('d', path)
			.attr('fill', function(d) {
				return color(d.data.label);
			});

		arc.append('text')
			.attr("transform", function(d) {
				return "translate(" + label.centroid(d) + ")";
			})
			.attr("dy", "0.35em")
			.text(function(d) {
				return d.data.label;
			});
	</script>

	<script>
		var size_untagged = <?php echo $totalFilesize['untagged'] ?>;
		var size_delete = <?php echo $totalFilesize['delete'] ?>;
		var size_archive = <?php echo $totalFilesize['archive'] ?>;
		var size_keep = <?php echo $totalFilesize['keep'] ?>;

		var dataset = [{
			label: 'untagged',
			size: size_untagged
		}, {
			label: 'delete',
			size: size_delete
		}, {
			label: 'archive',
			size: size_archive
		}, {
			label: 'keep',
			size: size_keep
		}];

		var width = 200;
		var height = 200;
		var radius = Math.min(width, height) / 2;

		var color = d3.scale.ordinal()
			//.range(["#98abc5", "#8a89a6", "#7b6888", "#6b486b", "#a05d56", "#d0743c", "#ff8c00"]);
		.range(["#666666", "#F69327", "#65C165", "#52A3BB"]);

		var svg = d3.select("#filesizechart")
			.append('svg')
			.attr('width', width)
			.attr('height', height)
			.append('g')
			.attr('transform', 'translate(' + width / 2 + ',' + height / 2 + ')');

		var pie = d3.layout.pie()
			.value(function(d) {
				return d.size;
			})
			.sort(null);

		var path = d3.svg.arc()
			.outerRadius(radius - 10)
			.innerRadius(0);

		var label = d3.svg.arc()
			.outerRadius(radius - 40)
			.innerRadius(radius - 40);

		var arc = svg.selectAll('.arc')
			.data(pie(dataset))
			.enter().append('g')
			.attr('class', 'arc');

		arc.append('path')
			.attr('d', path)
			.attr('fill', function(d) {
				return color(d.data.label);
			});

		arc.append('text')
			.attr("transform", function(d) {
				return "translate(" + label.centroid(d) + ")";
			})
			.attr("dy", "0.35em")
			.text(function(d) {
				return d.data.label;
			});
	</script>
    <script>
		var size_total = <?php echo $diskspace_total; ?>;
		var size_used = <?php echo $diskspace_used; ?>;
		var size_free = <?php echo $diskspace_free; ?>;
		var size_available = <?php echo $diskspace_available; ?>;

		var height = 20,
            maxBarWidth = 400;

		var svg = d3.select("#diskspacechart")
			.append('svg')
			.attr('width', maxBarWidth)
			.attr('height', height)
			.append('g');

        var bar = svg.selectAll('.bar')
			.data([size_used])
			.enter().append('g')
			.attr('class', 'bar');

		bar.append('rect')
            .attr('height', height)
            .attr('class', 'bar')
            .attr('width', function(d) {
                percent = parseInt(d / size_total * 100) + "%";
                return percent;
            });

        var label = svg.selectAll(".label")
            .data([size_used])
            .enter()
            .append('text')
            .attr('transform', 'translate(' + maxBarWidth / 2 + ',0)')
            .attr("dy", "1.35em")
            .attr('class', 'label')
            .attr('text-anchor', 'middle')
            .text(function(d) {
                percent = d3.round(d / size_total * 100, 2) + "%";
                return percent + ' used';
            });

	</script>
    <script>
		var size_total = <?php echo $diskspace_total; ?>;
        var size_indexed = <?php echo $totalFilesizeAll; ?>;

		var height = 16,
            maxBarWidth = 400;

		var svg = d3.select("#diskspacechart-indexed")
			.append('svg')
			.attr('width', maxBarWidth)
			.attr('height', height)
			.append('g');

        var bar = svg.selectAll('.bar')
			.data([size_indexed])
			.enter().append('g')
			.attr('class', 'bar');

		bar.append('rect')
            .attr('height', height)
            .attr('class', 'bar')
            .attr('width', function(d) {
                percent = parseInt(d / size_total * 100) + "%";
                return percent;
            });

        var label = svg.selectAll(".label")
            .data([size_indexed])
            .enter()
            .append('text')
            .attr('transform', 'translate(' + maxBarWidth / 2 + ',0)')
            .attr("dy", "1.3em")
            .attr('class', 'label')
            .attr('text-anchor', 'middle')
            .text(function(d) {
                percent = d3.round(d / size_total * 100, 2) + "%";
                return percent + ' indexed';
            });

	</script>
</body>
</html>
