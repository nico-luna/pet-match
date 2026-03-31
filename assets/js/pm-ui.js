(function($){
  function initToggles(root){
    var $root = root ? $(root) : $(document);
    $root.find('[data-pm-toggle]').off('click.pmToggle').on('click.pmToggle', function(){
      var key = $(this).attr('data-pm-toggle');
      if(!key) return;
      // Prefer a panel inside the same card/section
      var $panel = $(this).closest('.pm-card, .pm-case-report, section, div').find('[data-pm-panel="'+key+'"]').first();
      if(!$panel.length) $panel = $('[data-pm-panel="'+key+'"]').first();
      if(!$panel.length) return;
      if($panel.is('[hidden]')) $panel.removeAttr('hidden'); else $panel.attr('hidden','hidden');
    });
  }

  function initSlider($slider){
    var $track = $slider.find('.pm-slider-track');
    if(!$track.length) return;

    function getStep(){
      var $card = $track.find('.pm-card').first();
      return $card.length ? $card.outerWidth(true) : 320;
    }

    function syncButtons(){
      var el = $track.get(0);
      if(!el) return;
      var left = el.scrollLeft;
      var max  = el.scrollWidth - el.clientWidth;
      var atStart = left <= 2;
      var atEnd   = left >= (max - 2);
      $slider.find('.pm-slider-btn[data-dir="prev"]').prop('disabled', atStart).attr('aria-disabled', atStart ? 'true' : 'false');
      $slider.find('.pm-slider-btn[data-dir="next"]').prop('disabled', atEnd).attr('aria-disabled', atEnd ? 'true' : 'false');
    }

    $slider.find('.pm-slider-btn').off('click.pm').on('click.pm', function(){
      var dir = $(this).data('dir');
      var step = getStep();
      var delta = (dir === 'prev') ? -step : step;
      if ($track[0] && $track[0].scrollBy) {
        $track[0].scrollBy({ left: delta, top: 0, behavior: 'smooth' });
      } else {
        $track.scrollLeft($track.scrollLeft() + delta);
      }
    });

    $track.off('scroll.pm').on('scroll.pm', function(){
      // cheap debounce
      window.clearTimeout($slider.data('pmScrollT'));
      $slider.data('pmScrollT', window.setTimeout(syncButtons, 60));
    });

    // Initial state
    syncButtons();
  }

  function initGallery(root){
    var $root = root ? $(root) : $(document);
    $root.find('[data-pm-gallery]').each(function(){
      var $gallery = $(this);
      if ($gallery.data('pmGalleryInited')) return;
      $gallery.data('pmGalleryInited', true);

      var $stage = $gallery.find('[data-pm-gallery-stage]').first();
      var $thumbs = $gallery.find('[data-pm-gallery-thumb]');
      var $lightbox = $gallery.next('[data-pm-gallery-lightbox]');
      var $lightboxImage = $lightbox.find('[data-pm-gallery-lightbox-image]').first();

      function setActive($thumb){
        if (!$thumb.length) return;
        $thumbs.removeClass('is-active').attr('aria-pressed', 'false');
        $thumb.addClass('is-active').attr('aria-pressed', 'true');

        var large = $thumb.attr('data-pm-gallery-large') || '';
        var full = $thumb.attr('data-pm-gallery-full') || large;
        var alt = $thumb.attr('data-pm-gallery-alt') || '';

        var buttonHtml = '<button type="button" class="pm-case-gallery-main-btn" data-pm-gallery-open data-pm-gallery-src="' + full + '" data-pm-gallery-alt="' + alt.replace(/"/g, '&quot;') + '" aria-label="Ver imagen en grande">'
          + '<img class="pm-case-gallery-main-image" src="' + large + '" alt="' + alt.replace(/"/g, '&quot;') + '" loading="eager">'
          + '</button>';
        $stage.html(buttonHtml);
      }

      $thumbs.each(function(index){
        $(this).attr('aria-pressed', $(this).hasClass('is-active') ? 'true' : 'false');
        if (index === 0 && !$thumbs.filter('.is-active').length) {
          $(this).addClass('is-active').attr('aria-pressed', 'true');
        }
      });

      $thumbs.off('click.pmGallery').on('click.pmGallery', function(){
        setActive($(this));
      });

      $gallery.off('click.pmGalleryOpen').on('click.pmGalleryOpen', '[data-pm-gallery-open]', function(){
        if (!$lightbox.length || !$lightboxImage.length) return;
        var src = $(this).attr('data-pm-gallery-src') || '';
        var alt = $(this).attr('data-pm-gallery-alt') || '';
        if (!src) return;
        $lightboxImage.attr('src', src).attr('alt', alt);
        $lightbox.removeAttr('hidden').addClass('is-open');
        $('body').addClass('pm-gallery-open');
      });

      $lightbox.off('click.pmGalleryClose').on('click.pmGalleryClose', function(e){
        if ($(e.target).is('[data-pm-gallery-close], [data-pm-gallery-lightbox]')) {
          $lightbox.attr('hidden', 'hidden').removeClass('is-open');
          $('body').removeClass('pm-gallery-open');
        }
      });
    });
  }

  $(document).ready(function(){
    $('.pm-slider').each(function(){ initSlider($(this)); });
    initToggles(document);
    initGallery(document);
  });

  $(document).on('elementor/frontend/init', function(){
    $('.pm-slider').each(function(){ initSlider($(this)); });
    initToggles(document);
    initGallery(document);
  });

  $(document).on('keydown', function(e){
    if (e.key !== 'Escape') return;
    $('[data-pm-gallery-lightbox].is-open').attr('hidden', 'hidden').removeClass('is-open');
    $('body').removeClass('pm-gallery-open');
  });
})(jQuery);


// WhatsApp CTA wiring
document.addEventListener('click', function (e) {
  const btn = e.target.closest && e.target.closest('.pm-wa-btn');
  if (!btn) return;
  const wrap = btn.closest('.pm-wa-cta');
  if (!wrap) return;
  const phone = (wrap.getAttribute('data-pm-wa') || '').replace(/\D+/g, '');
  if (!phone) { e.preventDefault(); return; }
  const ta = wrap.querySelector('textarea');
  const msg = ta ? ta.value.trim() : '';
  const text = encodeURIComponent(msg || 'Hola! Te escribo por el caso publicado en Pet Match.');
  btn.href = 'https://wa.me/' + phone + '?text=' + text;
});
