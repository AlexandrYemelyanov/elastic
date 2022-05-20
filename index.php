<?php
use Elasticsearch\ClientBuilder;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

class ElasticsearchSandbox
{
    private $index = 'tests';
    private $type = 'product';

    public function execute()
    {
        require 'vendor/autoload.php';

        $this->client = ClientBuilder::create()
            ->setHosts(['localhost:9200'])
            ->build();

        try {

/*
            $response = $this->deleteIndex();
            $response = $this->createIndex();
            $response = $this->bulkIndexing($this->paramsForIndexing());
*/

            $response = $this->searchString('айфон (красный OR green)');
            $response2 = $this->getAll();

        } catch (Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            $response = 'Не найдено';
        }

        echo "<p><b>response</b><pre>";print_r($response);echo "</pre></p>";
        echo "<p><b>response2</b><pre>";print_r($response2);echo "</pre></p>";

        die;

    }

    //--- Поиск точного совпадения
    private function search( $query, $field = 'name')
    {
        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'body' => [
                'query' => [
                    'bool' => [
                        'should' => [

                            [
                                'match' => [
                                    $field => [
                                        'query' => $query,
                                        'operator' => 'and'
                                    ]
                                ]
                            ],

                            [
                                'match_phrase_prefix' => [
                                    $field => [
                                        'query' => $query,
                                        "max_expansions" => 10
                                    ]
                                ]
                            ],

                            [
                                'prefix' => [
                                    $field => $query
                                ]
                            ]
                        ]
                    ]

                ],
                /*
                                'sort' => [
                                    'create_at' => [
                                        'order' => 'desc'
                                    ]
                                ],
                */
                'highlight' => [
                    'fields' => [
                        $field => []
                    ]
                ],
                'from' => 0,
                'size' => 10000
            ]
        ];

        //   echo "<p><b>params</b><pre>";print_r($params);echo "</pre></p>";


        return $this->client->search($params);
    }

    //--- Поиск точного совпадения
    private function searchString( $query )
    {
        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'body' => [
                'query' => [
                    'query_string' => [
                        'query' => $query

                    ]

                ],

                'from' => 0,
                'size' => 10000
            ]
        ];

        echo "<p><b>params</b><pre>";print_r($params);echo "</pre></p>";


        return $this->client->search($params);
    }

    private function searchSuggest( $query, $field = 'name')
    {
        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'body' => [
                "suggest" => [
                    "name-suggest"  => [
                        "prefix"  => $query,
                        "completion"  => [
                            "field"  => "name_suggest"
                        ]
                    ]
                ]
            ]
        ];


        return $this->client->search($params);
    }

    //--- Получение всех документов
    private function getAll()
    {
        $params = [
            'index' => $this->index,
            'type' => $this->type,

        ];

        return $this->client->search($params);
    }

    private function createIndex()
    {
        $synonym_path = __DIR__."/synonyms.txt";

        $params = [
            'index' => $this->index,
            'include_type_name' => true,
            'body' => [
                'settings' => [
                    "index" => [
                        "analysis" => [
                            "analyzer" => [
                                "name_analyzer" => [
                                    "type" => "custom",
                                    "tokenizer" => "standard",
                                    "char_filter" => [
                                        "html_strip",
                                        "comma_to_dot_char_filter"
                                    ],
                                    "filter" => [
                                        "word_delimeter_filter",
                                        "synonym_filter",
                                        "lowercase",
                                        "english_stop",
                                        "english_stemmer",
                                        "english_possessive_stemmer"
                                    ]
                                ]
                            ],
                            "filter" => [
                                "synonym_filter" => [
                                    "type" => "synonym_graph",
                                    "synonyms" => explode( "\n", file_get_contents($synonym_path) )
                                ],
                                "word_delimeter_filter" => [
                                    "type" => "word_delimiter",
                                    "type_table" => [
                                        ". => DIGIT", # чтобы попадали в термы вещественные числа
                                        "- => ALPHANUM",
                                        "; => SUBWORD_DELIM",
                                        "` => SUBWORD_DELIM"
                                    ]
                                ],
                                "english_stop" => [
                                    "type" =>       "stop",
                                    "stopwords" =>  "_english_"
                                ],
                                "english_stemmer" => [
                                    "type" =>       "stemmer",
                                    "language" =>   "english"
                                ],
                                "english_possessive_stemmer" => [
                                    "type" =>       "stemmer",
                                    "language" =>   "possessive_english"
                                ]
                            ],
                            "char_filter" => [
                                "comma_to_dot_char_filter" => [
                                    "type" => "mapping",
                                    "mappings" => [
                                        ", => ."
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "mappings" => [
                    $this->type => [
                        'properties' => [
                            "name" => [
                                "type" => "text",
                                "analyzer" => "name_analyzer"
                            ],
                            "category" => [
                                "type" => "text",
                                "analyzer" => "name_analyzer"
                            ],
                            "name_suggest" => [
                                "type" => "completion",
                                "analyzer" => "name_analyzer"
                            ],
                            "create_at" => [
                                "type" => "date",
                                "format" => "yyyy-MM-dd HH:mm:ss"
                            ],
                            "url" => [
                                "type" => "text"
                            ],
                            "price" => [
                                "type" => "float"
                            ],
                            "image_filename" => [
                                "type" => "text"
                            ],
                            "ext" => [
                                "type" => "text"
                            ]
                        ]
                    ]
                ]
            ]

        ];

         return $this->client->indices()->create($params);
    }

    private function deleteIndex()
    {
        $params = [
            'index' => $this->index
        ];

        return $this->client->indices()->delete($params);
    }

    //--- Удаление документа по id
    private function deleteById($id)
    {
        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'id' => $id
        ];

        return $this->client->delete($params);
    }

    //--- Получение документа по id
    private function getById($id)
    {
        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'id' => $id
        ];

        return $this->client->getSource($params);
    }

    //--- Массовая индексация
    private function bulkIndexing($params)
    {
        return $this->client->bulk($params);
    }

    //--- Подгодовка параметров для индексации
    private function paramsForIndexing()
    {
        $params['body'][] = array(
            'index' => array(
                '_index' => $this->index,
                '_type' => $this->type,
                '_id' => 13170
            )
        );
        $params['body'][] = array(
            'name' => 'Apple iPhone 13 Pro 256GB Alpine Green',
            'category' => 'iPhone 13 Pro',
            'name_suggest' => 'Apple iPhone 13 Pro 256GB Alpine Green',
            'create_at' => '2022-03-10 17:16:49',
            'url' => 'apple-iphone-13-pro-256gb-alpine-green',
            'price' => '1319.0000',
            'image_id' => '23231',
            'image_filename' => '',
            'ext' => 'png'
        );

        $params['body'][] = array(
            'index' => array(
                '_index' => $this->index,
                '_type' => $this->type,
                '_id' => 13182
            )
        );
        $params['body'][] = array(
            'name' => 'Apple iPhone SE 128GB PRODUCT RED 2022',
            'category' => 'iPhone SE',
            'name_suggest' => 'Apple iPhone SE 128GB PRODUCT RED 2022',
            'create_at' => '2022-03-11 19:10:23',
            'url' => 'apple-iphone-se-128gb-product-red-2022',
            'price' => '0.0000',
            'image_id' => '23169',
            'image_filename' => '',
            'ext' => 'png'
        );

        $params['body'][] = array(
            'index' => array(
                '_index' => $this->index,
                '_type' => $this->type,
                '_id' => 11779
            )
        );
        $params['body'][] = array(
            'name' => 'Apple iPhone 12 256GB Purple (MJNQ3)',
            'category' => 'iPhone 12',
            'name_suggest' => 'Apple iPhone 12 256GB Purple (MJNQ3)',
            'create_at' => '2021-04-21 13:33:18',
            'url' => 'apple-iphone-12-256gb-purple',
            'price' => '999.0000',
            'image_id' => '15320',
            'image_filename' => '',
            'ext' => 'png'
        );


        $params['body'][] = array(
            'index' => array(
                '_index' => $this->index,
                '_type' => $this->type,
                '_id' => 10874
            )
        );
        $params['body'][] = array(
            'name' => 'Чохол Silicone Case для iPhone 12/12 Pro (FoxConn) (Red)',
            'category' => 'Чохли для iPhone',
            'name_suggest' => 'Чохол Silicone Case для iPhone 12/12 Pro (FoxConn) (Red)',
            'create_at' => '2020-11-18 19:05:04',
            'url' => 'chohol_silicone_case_dlya_iphone_12_12_pro_foxconn_red_',
            'price' => '20.6000',
            'image_id' => '14085',
            'image_filename' => '',
            'ext' => 'png'
        );


        $params['body'][] = array(
            'index' => array(
                '_index' => $this->index,
                '_type' => $this->type,
                '_id' => 12877
            )
        );
        $params['body'][] = array(
            'name' => 'Apple iPhone 12 Pro Max 128GB Graphite (Стан 10/10)',
            'category' => 'iPhone 12 Pro Max',
            'name_suggest' => 'Apple iPhone 12 Pro Max 128GB Graphite (Стан 10/10)',
            'create_at' => '2021-11-18 12:07:39',
            'url' => 'apple-iphone-12-pro-max-128gb-graphite-bu-1-1',
            'price' => '990.0000',
            'image_id' => '21812',
            'image_filename' => '',
            'ext' => 'png'
        );

        return $params;
    }

}

$shop_elastic = new ElasticsearchSandbox();
$shop_elastic->execute();