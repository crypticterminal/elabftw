<?php
/**
 * \Elabftw\Elabftw\Tags
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see http://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

use \PDO;
use \Exception;

/**
 * All about the tag
 */
class Tags
{
    /** pdo object */
    private $pdo;

    /** experiments or items */
    private $type;

    /**
     * Constructor
     *
     * @param string $type experiments or items
     */
    public function __construct($type)
    {
        $this->pdo = Db::getConnection();
        $this->type = $type;
    }

    /**
     * Create a tag
     *
     * @param string $tag
     * @param int $id
     */
    public function create($tag, $id)
    {
        // Sanitize tag, we remove '\' because it fucks up the javascript if you have this in the tags
        $tag = strtr(filter_var($tag, FILTER_SANITIZE_STRING), '\\', '');

        // check for string length and if user owns the experiment
        if (strlen($tag) < 1) {
            throw new Exception(_('Tag is too short!'));
        }

        if ($this->type === 'experiments' && !is_owned_by_user($id, $this->type, $_SESSION['userid'])) {
            throw new Exception(_('This section is out of your reach!'));
        }

        // TODO change userid for team because now the tags are not personnal but shared with team
        if ($this->type === 'experiments') {
            $sql = "INSERT INTO " . $this->type . "_tags (tag, item_id, userid) VALUES(:tag, :item_id, :userid)";
            $req = $this->pdo->prepare($sql);
            $req->bindParam(':userid', $_SESSION['userid'], PDO::PARAM_INT);
        } else {
            $sql = "INSERT INTO " . $this->type . "_tags (tag, item_id, team_id) VALUES(:tag, :item_id, :team_id)";
            $req = $this->pdo->prepare($sql);
            $req->bindParam(':team_id', $_SESSION['team_id'], PDO::PARAM_INT);
        }
        $req->bindParam(':tag', $tag, PDO::PARAM_STR);
        $req->bindParam(':item_id', $id, PDO::PARAM_INT);

        return $req->execute();
    }

    /**
     * Copy the tags from one experiment/item to an other.
     *
     * @param int $id The id of the original experiment/item
     * @param int $newId The id of the new experiment/item that will receive the tags
     * @return null
     */
    public function copyTags($id, $newId)
    {
        // TAGS
        if ($this->type === 'experiments') {
            $sql = "SELECT tag FROM experiments_tags WHERE item_id = :id";
        } else {
            $sql = "SELECT tag FROM items_tags WHERE item_id = :id";
        }
        $req = $this->pdo->prepare($sql);
        $req->bindParam(':id', $id);
        $req->execute();
        if ($req->rowCount() > 0) {
            while ($tags = $req->fetch()) {
                // Put them in the new one. here $newId is the new exp created
                if ($this->type === 'experiments') {
                    $sql = "INSERT INTO experiments_tags(tag, item_id, userid) VALUES(:tag, :item_id, :userid)";
                    $reqtag = $this->pdo->prepare($sql);
                    $reqtag->bindParam(':tag', $tags['tag']);
                    $reqtag->bindParam(':item_id', $newId);
                    $reqtag->bindParam(':userid', $_SESSION['userid']);
                } else {
                    $sql = "INSERT INTO items_tags(tag, item_id) VALUES(:tag, :item_id)";
                    $reqtag = $this->pdo->prepare($sql);
                    $reqtag->bindParam(':tag', $tags['tag']);
                    $reqtag->bindParam(':item_id', $newId);
                }
                $reqtag->execute();
            }
        }
    }


    /**
     * Generate a JS list for tags autocomplete
     *
     * @param string $type
     * @return string
     */
    public function generateTagList()
    {
        if ($this->type === 'experiments') {
            $sql = "SELECT DISTINCT tag, id FROM experiments_tags
                INNER JOIN users ON (experiments_tags.userid = users.userid)
                WHERE users.team = :team ORDER BY id DESC";
        } else {
            $sql = "SELECT DISTINCT tag, id FROM items_tags WHERE team_id = :team ORDER BY id DESC";
        }
        $req = $this->pdo->prepare($sql);
        $req->bindParam(':team', $_SESSION['team_id']);
        $req->execute();

        $tagList = "";
        while ($tag = $req->fetch()) {
            $tagList .= "'" . $tag[0] . "',";
        }

        return $tagList;
    }
}