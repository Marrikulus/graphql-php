<?hh //strict
//decl
namespace Utils;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\TypeInfo;

class ExtractTypesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ObjectType
     */
    private $query;

    /**
     * @var ObjectType
     */
    private $mutation;

    /**
     * @var InterfaceType
     */
    private $node;

    /**
     * @var InterfaceType
     */
    private $content;

    /**
     * @var ObjectType
     */
    private $blogStory;

    /**
     * @var ObjectType
     */
    private $link;

    /**
     * @var ObjectType
     */
    private $video;

    /**
     * @var ObjectType
     */
    private $videoMetadata;

    /**
     * @var ObjectType
     */
    private $comment;

    /**
     * @var ObjectType
     */
    private $user;

    /**
     * @var ObjectType
     */
    private $category;

    /**
     * @var UnionType
     */
    private $mention;

    private $postStoryMutation;

    private $postStoryMutationInput;

    private $postCommentMutation;

    private $postCommentMutationInput;

    public function setUp()
    {
        $this->node = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'id' => GraphQlType::string()
            ]
        ]);

        $this->content = new InterfaceType([
            'name' => 'Content',
            'fields' => function() {
                return [
                    'title' => GraphQlType::string(),
                    'body' => GraphQlType::string(),
                    'author' => $this->user,
                    'comments' => GraphQlType::listOf($this->comment),
                    'categories' => GraphQlType::listOf($this->category)
                ];
            }
        ]);

        $this->blogStory = new ObjectType([
            'name' => 'BlogStory',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories')
                ];
            },
        ]);

        $this->link = new ObjectType([
            'name' => 'Link',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories'),
                    'url' => GraphQlType::string()
                ];
            },
        ]);

        $this->video = new ObjectType([
            'name' => 'Video',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories'),
                    'streamUrl' => GraphQlType::string(),
                    'downloadUrl' => GraphQlType::string(),
                    'metadata' => $this->videoMetadata = new ObjectType([
                        'name' => 'VideoMetadata',
                        'fields' => [
                            'lat' => GraphQlType::float(),
                            'lng' => GraphQlType::float()
                        ]
                    ])
                ];
            }
        ]);

        $this->comment = new ObjectType([
            'name' => 'Comment',
            'interfaces' => [
                $this->node
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    'author' => $this->user,
                    'text' => GraphQlType::string(),
                    'replies' => GraphQlType::listOf($this->comment),
                    'parent' => $this->comment,
                    'content' => $this->content
                ];
            }
        ]);

        $this->user = new ObjectType([
            'name' => 'User',
            'interfaces' => [
                $this->node
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    'name' => GraphQlType::string(),
                ];
            }
        ]);

        $this->category = new ObjectType([
            'name' => 'Category',
            'interfaces' => [
                $this->node
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    'name' => GraphQlType::string()
                ];
            }
        ]);

        $this->mention = new UnionType([
            'name' => 'Mention',
            'types' => [
                $this->user,
                $this->category
            ]
        ]);

        $this->query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'viewer' => $this->user,
                'latestContent' => $this->content,
                'node' => $this->node,
                'mentions' => GraphQlType::listOf($this->mention)
            ]
        ]);

        $this->mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'postStory' => [
                    'type' => $this->postStoryMutation = new ObjectType([
                        'name' => 'PostStoryMutation',
                        'fields' => [
                            'story' => $this->blogStory
                        ]
                    ]),
                    'args' => [
                        'input' => GraphQlType::nonNull($this->postStoryMutationInput = new InputObjectType([
                            'name' => 'PostStoryMutationInput',
                            'fields' => [
                                'title' => GraphQlType::string(),
                                'body' => GraphQlType::string(),
                                'author' => GraphQlType::id(),
                                'category' => GraphQlType::id()
                            ]
                        ])),
                        'clientRequestId' => GraphQlType::string()
                    ]
                ],
                'postComment' => [
                    'type' => $this->postCommentMutation = new ObjectType([
                        'name' => 'PostCommentMutation',
                        'fields' => [
                            'comment' => $this->comment
                        ]
                    ]),
                    'args' => [
                        'input' => GraphQlType::nonNull($this->postCommentMutationInput = new InputObjectType([
                            'name' => 'PostCommentMutationInput',
                            'fields' => [
                                'text' => GraphQlType::nonNull(GraphQlType::string()),
                                'author' => GraphQlType::nonNull(GraphQlType::id()),
                                'content' => GraphQlType::id(),
                                'parent' => GraphQlType::id()
                            ]
                        ])),
                        'clientRequestId' => GraphQlType::string()
                    ]
                ]
            ]
        ]);
    }

    public function testExtractTypesFromQuery()
    {
        $expectedTypeMap = [
            'Query' => $this->query,
            'User' => $this->user,
            'Node' => $this->node,
            'String' => GraphQlType::string(),
            'Content' => $this->content,
            'Comment' => $this->comment,
            'Mention' => $this->mention,
            'Category' => $this->category,
        ];

        $actualTypeMap = TypeInfo::extractTypes($this->query);
        $this->assertEquals($expectedTypeMap, $actualTypeMap);
    }

    public function testExtractTypesFromMutation()
    {
        $expectedTypeMap = [
            'Mutation' => $this->mutation,
            'User' => $this->user,
            'Node' => $this->node,
            'String' => GraphQlType::string(),
            'Content' => $this->content,
            'Comment' => $this->comment,
            'BlogStory' => $this->blogStory,
            'Category' => $this->category,
            'PostStoryMutationInput' => $this->postStoryMutationInput,
            'ID' => GraphQlType::id(),
            'PostStoryMutation' => $this->postStoryMutation,
            'PostCommentMutationInput' => $this->postCommentMutationInput,
            'PostCommentMutation' => $this->postCommentMutation,
        ];

        $actualTypeMap = TypeInfo::extractTypes($this->mutation);
        $this->assertEquals($expectedTypeMap, $actualTypeMap);
    }

    public function testThrowsOnMultipleTypesWithSameName()
    {
        $otherUserType = new ObjectType([
            'name' => 'User',
            'fields' => ['a' => GraphQlType::string()]
        ]);

        $queryType = new ObjectType([
            'name' => 'Test',
            'fields' => [
                'otherUser' => $otherUserType,
                'user' => $this->user
            ]
        ]);

        $this->setExpectedException(
            '\GraphQL\Error\InvariantViolation',
            "Schema must contain unique named types but contains multiple types named \"User\""
        );
        TypeInfo::extractTypes($queryType);
    }
}
