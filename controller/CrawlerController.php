<?php
define('VIEW_PATH', ROOT . 'view/admin/');

use QL\QueryList;

class CrawlerController
{

  function index()
  {
    $tasks = config('@crawler');
    if (!empty($_POST['add_task'])) {
      $crawler = $this->add_task();
    } else if (!empty($_POST['add_field'])) {
      $crawler = $this->add_task();
      array_push($crawler['fields'], []);
    } else if (isset($_POST['delete_field'])) {
      $crawler = $this->add_task();
      unset($crawler['fields'][$_POST['delete_field']]);
    } else if (!empty($_POST['edit_task'])) {
      $crawler = $tasks[$_POST['edit_task']];
      $crawler['slug'] = $_POST['edit_task'];
    } else if (!empty($_POST['delete_task'])) {
      unset($tasks[$_POST['delete_task']]);
      config('@crawler', (array)$tasks);
    } else if (!empty($_POST['save_task'])) {
      $crawler = $this->add_task();
      $tasks[$crawler['slug']] = $crawler;
      if (isset($_POST['old_task_slug']) && $_POST['old_task_slug'] != $_POST['task_slug']) {
        unset($tasks[$_POST['old_task_slug']]);
      }
      unset($crawler);
      config('@crawler', (array)$tasks);
    }
    foreach ($tasks as $slug => $task) {
      $tasks[$slug] = $this->get_collect($slug);
    }
    return view::load('crawler')->with('tasks', $tasks)->with('crawler', $crawler);
  }

  private function add_task()
  {
    $fields = [];
    for ($i = 0; $i < sizeof(array_filter(array_keys($_POST), function ($name) {
      return stripos($name, 'field_name') === 0;
    })); $i++) {
      array_push($fields, [
        "name" => $_POST['field_name_' . $i],
        "label" => $_POST['field_label_' . $i],
        "find" => $_POST['field_find_' . $i],
        "func" => $_POST['field_func_' . $i],
        "args" => $_POST['field_args_' . $i],
        "down" => $_POST['field_down_' . $i]
      ]);
    }
    return [
      "name" => isset($_POST['task_name']) ? $_POST['task_name'] : "新爬虫任务",
      "slug" => isset($_POST['task_slug']) ? $_POST['task_slug'] : "new_crawler_task",
      'domains' => isset($_POST['task_domains']) ? explode("\n", $_POST['task_domains'])  : [],
      'scan_urls' => isset($_POST['task_scan_urls']) ? explode("\n", $_POST['task_scan_urls']) : [],
      'list_url_regexes' => isset($_POST['task_list_url_regexes']) ? explode("\n", $_POST['task_list_url_regexes']) : [],
      'content_url_regexes' => isset($_POST['task_content_url_regexes']) ? explode("\n", $_POST['task_content_url_regexes']) : [],
      'download_url_regexes' => isset($_POST['task_download_url_regexes']) ? explode("\n", $_POST['task_download_url_regexes']) : [],
      'fields' => $fields,
    ];
  }

  static function get_collect($slug)
  {
    $task = config('@crawler')[$slug];
    $task['slug'] = $slug;
    $task['local_path'] = "/../upload/.crawler/$slug";
    // 检测文件夹是否存在，不存在则创建
    if (!file_exists(__DIR__ . $task['local_path'] . "/collected_contents")) {
      array_reduce(explode('/', $task['local_path'] . "/collected_contents"), function ($parent, $dir) {
        if (!empty($parent)) {
          $dir = $parent . "/" . $dir;
        }
        if (!file_exists(__DIR__ . '/' . $dir)) mkdir(__DIR__ . '/' . $dir);
        return $dir;
      }, '');
    }
    $task['collect_scan_urls'] = $task['scan_urls'];
    $task['collected_scan_urls_num'] = 0;
    if (file_exists(__DIR__ . $task['local_path'] . "/collected_scan_urls.txt") && ($fp = fopen(__DIR__ . $task['local_path'] . "/collected_scan_urls.txt", "r")) !== FALSE) {
      while (fgetcsv($fp) !== FALSE) {
        $task['collected_scan_urls_num']++;
      }
    }
    // 待请求列表页的 url
    $task['collect_list_urls'] = [];
    if (file_exists(__DIR__ . $task['local_path'] . "/collect_list_urls.txt"))
      $task['collect_list_urls'] = explode("\n", file_get_contents(__DIR__ . $task['local_path'] . "/collect_list_urls.txt"));

    $task['collected_list_urls_num'] = 0;
    if (file_exists(__DIR__ . $task['local_path'] . "/collected_list_urls.txt") && ($fp = fopen(__DIR__ . $task['local_path'] . "/collected_list_urls.txt", "r")) !== FALSE) {
      while (fgetcsv($fp) !== FALSE) {
        $task['collected_list_urls_num']++;
      }
    }
    // 待请求内容页的 url
    $task['collect_content_urls'] = [];
    if (file_exists(__DIR__ . $task['local_path'] . "/collect_content_urls.txt")) {
      $task['collect_content_urls'] = explode("\n", file_get_contents(__DIR__ . $task['local_path'] . "/collect_content_urls.txt"));
    }
    $task['collected_content_urls_num'] = 0;
    if (file_exists(__DIR__ . $task['local_path'] . "/collected_contents")) {
      $task['collected_content_urls_num'] = sizeof(scandir(__DIR__ . $task['local_path'] . "/collected_contents")) - 2;
    }
    // 已抽取内容页
    $task['collected_content_fields_num'] = 0;
    if (file_exists(__DIR__ . $task['local_path'] . "/collected_content_fields.csv")) {
      $task['collected_content_fields_num'] = sizeof(explode("\n", file_get_contents(__DIR__ . $task['local_path'] . "/collected_content_fields.csv"))) - 1;
    }
    return $task;
  }

  static function start()
  {
    $slug = $_POST['start_crawler_task'];
    $task = self::get_collect($slug);
    $get_list_url = function ($link) use (&$task) {
      if (empty($link)) return;
      foreach ($task['list_url_regexes'] as $list_url_regex) {
        $list_url_regex_parse = parse_url($list_url_regex);
        $list_url_regex = "/^" . $list_url_regex_parse["scheme"] . ":\/\/" . preg_quote($list_url_regex_parse["host"]) . "\\" . $list_url_regex_parse["path"] . "$/";
        if (preg_match($list_url_regex, $link) && !in_array($link, $task['collect_list_urls'])) {
          array_push($task['collect_list_urls'], $link);
          echo "<script>$('[name=collect_list_urls][task-name={$task['slug']}]').text(" . sizeof($task['collect_list_urls']) . ")</script>";
          $fp = fopen(__DIR__ . "{$task['local_path']}/collect_list_urls.txt", 'a'); //opens file in append mode  
          fwrite($fp, $link . "\n");
          fclose($fp);
          flush();
          break;
        }
      }
    };
    $get_content_url =  function ($link) use (&$task) {
      if (empty($link)) return;
      foreach ($task['content_url_regexes'] as $content_url_regex) {
        $content_url_regex_parse = parse_url($content_url_regex);
        $content_url_regex = "/" . $content_url_regex_parse["scheme"] . ":\/\/" . preg_quote($content_url_regex_parse["host"]) . "\\" . $content_url_regex_parse["path"] . "/";
        if (preg_match($content_url_regex, $link) && !in_array($link, $task['collect_content_urls'])) {
          array_push($task['collect_content_urls'], $link);
          echo "<script>$('[name=collect_content_urls][task-name={$task['slug']}]').text(" . sizeof($task['collect_content_urls']) . ")</script>";
          $fp = fopen(__DIR__ . "{$task['local_path']}/collect_content_urls.txt", 'a'); //opens file in append mode  
          fwrite($fp, $link . "\n");
          fclose($fp);
          flush();
          flush();
          break;
        }
      }
    };
    $get_scan_url = function ($url, $html = null) use (&$task, $get_list_url, $get_content_url) {
      if (empty($url)) return;
      $url_parse = parse_url($url);
      if (in_array($url_parse['host'], $task['domains'])) {
        // 请求页面
        if (empty($html)) $html = QueryList::get($url);
        $links = $html->find('a')->attrs('href')->all();
        foreach ($links as $link) {
          $link_parse = parse_url($link);
          // 匹配域名: 存在域名且域名不在域名数组中，结束本次循环
          if (isset($link_parse['host']) && !in_array($link_parse['host'], $task['domains'])) continue;
          $link_parse["scheme"] = isset($link_parse["scheme"]) ? $link_parse["scheme"] : $url_parse['scheme'];
          $link_parse["host"] = isset($link_parse["host"]) ? $link_parse["host"] : $url_parse['host'];
          $link = $link_parse["scheme"] . "://" . $link_parse["host"] . $link_parse["path"];
          // 匹配列表页
          $get_list_url($link);
          // 匹配内容页
          $get_content_url($link);
        }
      }
    };
    $get_content_fields = function ($link) use (&$task, $get_scan_url) {
      if (empty($link)) return;
      if (file_exists(__DIR__ . $task['local_path'] . "/collected_contents/" . basename($link))) {
        $html = file_get_contents(__DIR__ . $task['local_path'] . "/collected_contents/" . basename($link));
        $html = QueryList::html($html);
      } else {
        $html = QueryList::get($link);
        file_put_contents(__DIR__ . $task['local_path'] . "/collected_contents/" . basename($link), $html->getHtml());
      }
      $get_scan_url($link, $html);
      $content = [];
      try {
        foreach ($task['fields'] as $field) {
          if (!empty($field['find']) && !empty($field['func']))
            $value = $html->find($field['find'])->{$field['func']}(empty($field['args']) ? NULL : $field['args']);
          if (is_array($value) || is_object($value)) {
            $value = json_encode($value, true);
          }
          $content[$field['name']] = str_replace(',', '，', $value);
        }
      } catch (Exception $e) {
        return false;
      }
      return $content;
    };

    echo "<script>$('[name=start_crawler_task][task-name={$task['slug']}]').toggle()</script>";
    flush();

    while ($task['collected_scan_urls_num'] < sizeof($task['collect_scan_urls'])) {
      $url = $task['collect_scan_urls'][$task['collected_scan_urls_num']];
      $get_scan_url($url);
      echo "<script>$('[name=collected_scan_urls][task-name={$task['slug']}]').text(" . ($task['collected_scan_urls_num'] + 1) . ")</script>";
      $fp = fopen(__DIR__ . "{$task['local_path']}/collected_scan_urls.txt", 'a'); //opens file in append mode  
      fwrite($fp, $url . "\n");
      fclose($fp);
      flush();
      $task['collected_scan_urls_num']++;
    }
    while ($task['collected_content_urls_num'] < sizeof($task['collect_content_urls'])) {
      $url = $task['collect_content_urls'][$task['collected_content_urls_num']];
      echo "<script>$('[name=collected_content_urls][task-name={$task['slug']}]').text(" . ($task['collected_content_urls_num'] + 1) . ")</script>";
      $fields = $get_content_fields($url);
      $fp = fopen(__DIR__ . "{$task['local_path']}/collected_content_fields.csv", 'a'); //opens file in append mode  
      if ($task['collected_content_urls_num'] == 0) {
        fputcsv($fp, array_merge(['url'], array_keys($fields)));
      }
      if (!empty($fields)) {
        fputcsv($fp, array_merge([$url], array_values($fields)));
        var_dump($fields);
        echo "<script>$('[name=collected_content_fields][task-name={$task['slug']}]').text(" . ($task['collected_content_fields_num'] + 1) . ")</script>";
        $task['collected_content_fields_num']++;
      }
      fclose($fp);
      flush();
      $task['collected_content_urls_num']++;
    }
    while ($task['collected_list_urls_num'] < sizeof($task['collect_list_urls'])) {
      $url = $task['collect_list_urls'][$task['collected_list_urls_num']];
      $get_scan_url($url);
      echo "<script>$('[name=collected_list_urls][task-name={$task['slug']}]').text(" . ($task['collected_list_urls_num'] + 1) . ")</script>";
      $task['collected_list_urls_num']++;
      $fp = fopen(__DIR__ . "{$task['local_path']}/collected_list_urls.txt", 'a'); //opens file in append mode  
      fwrite($fp, $url . "\n");
      fclose($fp);
      flush();
    }

    echo "<script>$('[name=start_crawler_task][task-name={$task['slug']}]').toggle()</script>";
    echo "<script>$('[name=stop_task][task-name={$task['slug']}]').toggle()</script>";
    flush();
  }
}
