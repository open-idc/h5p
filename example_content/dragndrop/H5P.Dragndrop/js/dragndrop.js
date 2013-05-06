var H5P = H5P || {};

H5P.Dragndrop = function (options, contentId) {

  var target;
  var $ = H5P.jQuery;

  if ( !(this instanceof H5P.Dragndrop) ){
    return new H5P.Dragndrop(options, contentId);
  }

  var cp = H5P.getContentPath(contentId);

  var allAnswered = function() {
    var droppables = 0;
    var answers = 0;
    target.find('.droppable').each(function (idx, el) {
      droppables++;
      if($(el).data('content')) {
        answers++;
      }
    });
    return (droppables == answers);
  };

  var getMaxScore = function() {
    return target.find('.droppable').length;
  }

  var getScore = function() {
    var score = 0;
    target.find('.droppable').each(function (idx, el) {
      if($(el).data('content')) {
        var index = $(el).data('content').replace(/[a-z\-]+/,'');
        var target = options.draggables[index].target;
        if(target == el.id) {
          score++;
        }
      }
    });
    return score;
  }

  var showScore = function() {
    var score = 0;
    var count = 0;
    target.find('.droppable').each(function (idx, el) {
      count++;
      if($(el).data('content')) {
        var index = $(el).data('content').replace(/[a-z\-]+/,'');
        var target = options.draggables[index].target;
        if(target == el.id) {
          $(el).addClass('droppable-correct-answer');
          $(el).removeClass('droppable-wrong-answer');
          score++;
        }
        else {
          $(el).addClass('droppable-wrong-answer');
          $(el).removeClass('droppable-correct-answer');
        }
      }
    });
    $('#score').html(options.scoreText.replace('@score', score).replace('@total', count));
  }

  var attach = function(board) {
    var $ = H5P.jQuery;
    var droppables = options.droppables;
    var draggables = options.draggables;
    var panel = options.panel[0];
    var $dragndrop = $('<div class="dragndrop"></div>');

    target = typeof(board) === "string" ? $("#" + board) : $(board);

    target.html('<div class="dragndrop-title">'+panel.title+'</div>');

    $dragndrop.css({ width: panel.width, height: panel.height });
    if(panel.image){
      $dragndrop.css({ backgroundImage: 'url('+cp+panel.image.path+')' });
      $dragndrop.css({ backgroundPosition: panel.coords.x + 'px ' + panel.coords.y + 'px' });
    }
    target.append($dragndrop);

    var position = $dragndrop.position();

    function addElement(id, className, el) {
      var text = el.text ? el.text : '';
      var $el = $('<div class="'+className+'">'+text+'</div>');
      $dragndrop.append($el);
      if(id) {
        $el.attr('id', id);
      }
      if(el.scope) {
        $el.data('scope', el.scope);
      }
      if(el.height) {
        $el.css({ height: el.height });
      }
      if(el.width) {
        $el.css({ width: el.width });
      }
      if(el.coords) {
        $el.css({ left: el.coords.x + 'px', top: el.coords.y + 'px'});
        $el.data('x', el.coords.x);
        $el.data('y', el.coords.y);
      }
      return $el;
    }

    var buttons = Array();

    if($('.qs-footer').length) {
      // Hack to fix boardgame fit
      var w = target.parent().parent().innerWidth() - 2*parseInt(target.parent().css('paddingLeft'));
      $('.dragndrop-title').css('width', w);
    }
    else {
      // Add show score button when not boardgame
      var buttons = Array( { text: options.scoreShow, click: showScore, className: 'button show-score' });
    }


    // Add buttons
    for (var i = 0; i < buttons.length; i++) {
      $button = addElement(null, buttons[i].className, buttons[i]);
      $button.click(buttons[i].click);
    }

    // Add content
    for (var i = 0; i < options.content.length; i++) {
      $content = addElement(options.content[i].id, 'static ', options.content[i]);
    }

    // Add droppables
    for (var i = 0; i < droppables.length; i++) {
      $droppable = addElement(droppables[i].scope, 'droppable '+droppables[i].className, droppables[i]);
    }

    // Add draggables
    for (var i = 0; i < draggables.length; i++) {
      $draggable = addElement('draggable-'+i, 'draggable '+draggables[i].className, draggables[i]);
    }

    // Make droppables
    target.find('.droppable').each(function (idx, el) {
      $(el).droppable({
        scope: $(el).data('scope'),
        activeClass: 'droppable-active',
        fit: 'intersect',
        out: function(event, ui) {
          // TODO: somthing
        },
        drop: function(event, ui) {
          $(this).removeClass('droppable-wrong-answer');

          // If this drag was in a drop area and this drag is not the same
          if($(this).data('content') && ui.draggable.attr('id') != $(this).data('content')) {
            // Remove underlaying drag (move to initial position)
            var id = '#'+$(this).data('content');
            var index = $(this).data('content').replace(/[a-z\-]+/,'');
            $(id).data('content', null);
            $(id).animate({
              left: $(id).data('x'),
              top:  $(id).data('y')
            });
          }

          // Was object in another drop?
          if(ui.draggable.data('content')) {
            // Remove object from previous drop
            $('#'+ui.draggable.data('content')) = null;
          }

          // Set attributes
          $(this).data('content', ui.draggable.attr('id'));
          ui.draggable.data('content', $(this).attr('id'));
          ui.draggable.css('z-index', '1');

          // Move drag to center of drop
          ui.draggable.animate({
            top: Math.round(($(this).outerHeight() - ui.draggable.outerHeight()) / 2) + parseInt($(this).css('top')),
            left: Math.round(($(this).outerWidth() - ui.draggable.outerWidth()) / 2) + parseInt($(this).css('left'))
          });

          if(allAnswered()){
            $(returnObject).trigger('h5pQuestionAnswered');
          }
        },
      });
    });

    // Make draggables
    target.find('.draggable').each(function (idx, el) {
      $(el).draggable({
        scope: $(el).data('scope'),
        revert: 'invalid',
        start: function(event, ui) {
          ui.helper.css('z-index', '2');
        },
        stop: function(event, ui) {
          // Todo something
        }
      });
    });

    return this;
  };

  var returnObject = {
    attach: attach,
    machineName: 'H5P.Dragndrop',
    getScore: function() {
      return getScore();
    },
    getAnswerGiven: function() {
      return allAnswered();
    },
    getMaxScore: function() {
      return getMaxScore();
    }
  };

  return returnObject;
};
