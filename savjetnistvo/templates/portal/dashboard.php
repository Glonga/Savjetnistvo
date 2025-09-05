<div class="sv-portal">
  <div class="sv-tabs">
    <button class="sv-tab sv-tab-active" data-tab="projects"><?php echo esc_html__('Projekti','savjetnistvo'); ?></button>
    <button class="sv-tab" data-tab="meetings"><?php echo esc_html__('Susreti','savjetnistvo'); ?></button>
    <button class="sv-tab" data-tab="payments"><?php echo esc_html__('Plaćanja','savjetnistvo'); ?></button>
    <button class="sv-tab" data-tab="profile"><?php echo esc_html__('Moji podaci','savjetnistvo'); ?></button>
  </div>

  <div class="sv-panels">
    <section data-panel="projects"></section>
    <section class="hidden" data-panel="meetings"></section>
    <section class="hidden" data-panel="payments"></section>
    <section class="hidden" data-panel="profile">
      <h3><?php echo esc_html__('Moji podaci', 'savjetnistvo'); ?></h3>
      <div class="sv-body">
        <div><strong><?php echo esc_html__('Ime', 'savjetnistvo'); ?>:</strong> <span data-me="display">—</span></div>
        <div><strong>Email:</strong> <span data-me="email">—</span></div>
        <div><strong><?php echo esc_html__('Pseudonim', 'savjetnistvo'); ?>:</strong> <span data-me="pseudonim">—</span></div>
        <div><strong><?php echo esc_html__('Telefon', 'savjetnistvo'); ?>:</strong> <span data-me="phone">—</span></div>
      </div>
    </section>
  </div>
</div>
