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

			var vote_total_count = jQuery( '#updown-' + element_name + '-' + element_id + ' .updown-total-count' );
			
      var vote_down_count = jQuery( '#updown-' + element_name + '-' + element_id + ' .updown-down-count' );
      var vote_down_button = jQuery( '#updown-' + element_name + '-' + element_id + ' .updown-down-button' );

      var vote_label = jQuery( '#updown-' + element_name + '-' + element_id + ' .updown-label' );;

      dehighlightButton( vote_down_button );
      dehighlightButton( vote_up_button );
			if ( vote_response.direction == 1 )
				highlightButton( vote_up_button );
			else if ( vote_response.direction == -1 )
				highlightButton( vote_down_button );

			if (vote_up_count.length && vote_down_count.length)
			{
				if ( vote_response.vote_totals.up == 0 )
					vote_up_count.removeClass( 'updown-active' );
				else
					vote_up_count.addClass( 'updown-active' );

				if ( vote_response.vote_totals.down == 0 )
					vote_down_count.removeClass( 'updown-active' );
				else
					vote_down_count.addClass( 'updown-active' );
			}

			if (vote_total_count.length)
			{
				var sign = vote_total_count.hasClass('updown-count-sign')
				var vote_total_count_text = vote_response.vote_totals.up - vote_response.vote_totals.down;
				vote_total_count.removeClass('updown-pos-count');
				vote_total_count.removeClass('updown-neg-count');
				if (vote_total_count_text > 0)
				{
					if (sign)
						vote_total_count_text = '+' + vote_total_count_text;
					vote_total_count.addClass( 'updown-pos-count');
				}
				else if (vote_total_count_text < 0)
				{
					vote_total_count.addClass( 'updown-neg-count');
					if (!sign)
						vote_total_count_text = ("" + vote_total_count_text).substr (1);
				}
				vote_total_count_num = parseInt(vote_response.vote_totals.up) + parseInt(vote_response.vote_totals.down);
				vote_total_count.attr ("title", vote_total_count_num + " vote" + (vote_total_count_num == 1 ? "" : "s") + " so far");
			}
			vote_total_count.text(vote_total_count_text);
			
			if ( vote_response.vote_totals.up == 0 && vote_response.vote_totals.down == 0)
			{
				if (vote_up_count.length)
        vote_up_count.hide();
				if (vote_down_count.length)
        vote_down_count.hide();
				if (vote_total_count.length)
					vote_total_count.hide ();
			}
			else
			{
				if ( vote_response.vote_totals.up > 0 )
					vote_response.vote_totals.up = "+" + vote_response.vote_totals.up;
				if ( vote_response.vote_totals.down > 0 )
					vote_response.vote_totals.down = "-" + vote_response.vote_totals.down;
        
				if (vote_up_count.length)
        vote_up_count.text(vote_response.vote_totals.up).show();
				if (vote_down_count.length)
        vote_down_count.text(vote_response.vote_totals.down).show();
				if (vote_total_count.length)
					vote_total_count.show ();
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

	jQuery('.updown-button').live( 'click', function( event )
	{
		var id = jQuery(this).parent().parent().attr('id').split("-");
    var vote_value = -1;
    var button_obj = jQuery(event.target);

    //Remove vote if clicking same vote again
		if ( button_obj.attr('src').indexOf('-on.png') >= 0 )
			vote_value = 0;
		else
			vote_value = button_obj.attr('vote-direction');

		if (id[1] == "post" && id[2])
			votePost(id[2], vote_value );
		if (id[1] == "comment" && id[2])
			voteComment(id[2], vote_value );
  });

});

