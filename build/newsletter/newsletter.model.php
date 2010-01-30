<?php
/**
 * News letter archive model
 * 
 * @package about
 * @author JeanYves
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License (GPL)
 * @version $Id$
 */
class NewsletterModel extends RoxModelBase
{
/*
// Load teh data for a news letter 
// @$LetterName is assumed to be the seed of an existing news letter
// for exemple: NewsJuly2007 to retrieve the words data for 	BroadCast_Title_NewsJuly2007
//														and for 	BroadCast_Body_NewsJuly2007
// it returns null if no entry is found or the number of pendinf to send and the number of send if the news letter exists
*/
    public function Load($LetterName) {
		$sql="select * from broadcast where Name='".$this->dao->escape($LetterName)."'" ;
//		die ($sql) ;
        $BroadCast=$this->singleLookup($sql) ;
		if (empty($BroadCast)) return(NULL) ;
		$Data->LetterName=$LetterName ;
		$Data->BroadCast=$BroadCast ;
		$sql="select count(*) as cnt from broadcastmessages where IdBroadCast=".$BroadCast->id." and Status='Send'" ;
        $rr=$this->singleLookup($sql) ;
		$Data->CountSent=$rr->cnt ;
		$sql="select count(*) as cnt from broadcastmessages where IdBroadCast=".$BroadCast->id." and Status='ToSend'" ;
        $rr=$this->singleLookup($sql) ;
		$Data->CountToSend=$rr->cnt ;
        return($Data) ;
    }

    public function PreviousLetters() {
		$sql="select * from broadcast where Status='Triggered' and type='Normal' order by created desc" ;
        $Data=$this->bulkLookup($sql) ;
		return($Data) ;
	}
}
?>
