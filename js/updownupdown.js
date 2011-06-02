jQuery(document).ready(function(){

  //Hide empty elements within vote badges
  jQuery( '.updown-vote-box div:empty' ).hide();

  function votePost( post_id, direction ) {
      var data = {
        action: 'register_vote',
        post_id: post_id,
        direction: direction
      };
      jQuery.post(UpDownUpDown.ajaxurl, data, function(response){ handleVoteCallback(response); });
  }

  function voteComment( comment_id, direction ) {
      var data = {
        action: 'register_vote',
        comment_id: comment_id,
        direction: direction
      };
      jQuery.post(UpDownUpDown.ajaxurl, data, function(response){ handleVoteCallback(response); });
  }

  function handleVoteCallback( response ) {

    var vote_response = jQuery.parseJSON(response);
    if ( vote_response.status == 1 ) {
      //Success, update vote count
      
      if ( vote_response.post_id ) {
        var element_name = 'post';
        var element_id = vote_response.post_id;
      } else if ( vote_response.comment_id ) {
        var element_name = 'comment';
        var element_id = vote_response.comment_id;
      }
      
      var vote_up_count = jQuery( '#updown-' + element_name + '-' + element_id + ' .updown-up-count' );
      var vote_up_button = jQuery( '#updown-' + element_name + '-' + element_id + ' .updown-up-button' );

      var vote_down_count = jQuery( '#updown-' + element_name + '-' + element_id + ' .updown-down-count' );
      var vote_down_button = jQuery( '#updown-' + element_name + '-' + element_id + ' .updown-down-button' );

      var vote_label = jQuery( '#updown-' + element_name + '-' + element_id + ' .updown-label' );;

      dehighlightButton( vote_down_button );
      dehighlightButton( vote_up_button );
      if ( vote_response.direction == 1 ) highlightButton( vote_up_button );
      else if ( vote_response.direction == -1 ) highlightButton( vote_down_button );

      if ( vote_response.vote_totals.up == 0 ) vote_up_count.removeClass( 'updown-active' );
      else vote_up_count.addClass( 'updown-active' );

      if ( vote_response.vote_totals.down == 0 ) vote_down_count.removeClass( 'updown-active' );
      else vote_down_count.addClass( 'updown-active' );

      if ( vote_response.vote_totals.up == 0 && vote_response.vote_totals.down == 0) {
        vote_up_count.hide();
        vote_down_count.hide();
      } else {
        if ( vote_response.vote_totals.up > 0 ) vote_response.vote_totals.up = "+" + vote_response.vote_totals.up;
        if ( vote_response.vote_totals.down > 0 ) vote_response.vote_totals.down = "-" + vote_response.vote_totals.down;
        
        vote_up_count.text(vote_response.vote_totals.up).show();
        vote_down_count.text(vote_response.vote_totals.down).show();
      }
    } else {
      //Failure, notify user
      if ( vote_response.message  != null ) { 
        alert( vote_response.message ); 
      }
    }
  }

  function highlightButton( buttonObj ) {
    buttonObj.attr( 'src', buttonObj.attr( 'src' ).replace( '.png', '-on.png' ) );
  }

  function dehighlightButton( buttonObj ) {
    buttonObj.attr( 'src', buttonObj.attr( 'src' ).replace( '-on.png', '.png' ) );
  }

  jQuery('.updown-button').live( 'click', function( event ) {
    var post_id = jQuery(this).parent().parent().attr('post-id');
    var comment_id = jQuery(this).parent().parent().attr('comment-id');    
    var vote_value = -1;
    var button_obj = jQuery(event.target);

    //Remove vote if clicking same vote again
    if ( button_obj.attr('src').indexOf('-on.png') >= 0 ) vote_value = 0;
    else vote_value = button_obj.attr('vote-direction');

    if ( post_id ) {
      votePost( post_id, vote_value );
    } else if ( comment_id ) {
      voteComment( comment_id, vote_value );
    }
  });

});

