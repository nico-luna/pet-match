<?php
final class PM_Pet_Match {
  const VERSION = "0.5.3.21";
  const CPT = 'pet_case';

  use PM_Pet_Match_Core_Trait;
  use PM_Pet_Match_Assets_Trait;
  use PM_Pet_Match_Taxonomies_Trait;
  use PM_Pet_Match_Frontend_Trait;
  use PM_Pet_Match_Forms_Trait;
  use PM_Pet_Match_Admin_Trait;
}
