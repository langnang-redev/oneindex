<?php
define('VIEW_PATH', ROOT . 'view/admin/');
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
      ]);
    }
    return [
      "name" => isset($_POST['task_name']) ? $_POST['task_name'] : "新爬虫任务",
      "slug" => isset($_POST['task_slug']) ? $_POST['task_slug'] : "new_crawler_task",
      'domains' => [],
      'scan_urls' => [],
      'list_url_regexes' => [],
      'content_url_regexes' => [],
      'fields' => $fields,
    ];
  }
}
