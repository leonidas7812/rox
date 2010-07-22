<?php
/*
Copyright (c) 2007-2009 BeVolunteer

This file is part of BW Rox.

BW Rox is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

BW Rox is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see <http://www.gnu.org/licenses/> or 
write to the Free Software Foundation, Inc., 59 Temple Place - Suite 330, 
Boston, MA  02111-1307, USA.
*/

//------------------------------------------------------------------------------------
/**
 * This widget shows the forum for a group page
 *
 */
class CommentsWidget  // extends ForumBoardWidget
{
    public function render()
    {
        echo 'Comment of the dat';
    }
    
    public function setGroup($group)
    {
        // extract information from the $group object
    }
}

//------------------------------------------------------------------------------------
/**
 * This widget shows a list of members with pictures.
 */
class GroupMemberlistWidget  // extends MemberlistWidget?
{
    private $_group;
    
    public function render()
    {
        $memberships = $this->_group->getMembers();
        for ($i = 0; $i < 6 && $i < count($memberships); $i++)
        {
            ?>
            <div class="groupmembers center float_left">                
                <?=MOD_layoutbits::PIC_50_50($memberships[$i]->Username) ?>
                <a href="members/<?=$memberships[$i]->Username ?>"><?=$memberships[$i]->Username ?></a>               
            </div>
            <?php
        }
    }
    
    public function setGroup($group)
    {
        // extract memberlist information from the $group object
        $this->_group = $group;
    }
}





?>
