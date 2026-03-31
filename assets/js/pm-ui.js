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

  $(document).ready(function(){
    $('.pm-slider').each(function(){ initSlider($(this)); });
    initToggles(document);
  });

  $(document).on('elementor/frontend/init', function(){
    $('.pm-slider').each(function(){ initSlider($(this)); });
    initToggles(document);
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
  const text = encodeURIComponent(msg || 'Hola! Te escribo por el caso publicado en Patitas.');
  btn.href = 'https://wa.me/' + phone + '?text=' + text;
});
