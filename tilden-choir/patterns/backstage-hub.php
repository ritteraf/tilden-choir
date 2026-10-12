<?php
/**
 * Title: Backstage hub
 * Slug: tilden-choir/backstage-hub
 * Categories: tilden-choir
 * Description: The page the Cast & Crew button lands on: this year's cantata as a hero, the next rehearsal, then a section each for everyone, singers, narrators, actors and tech crew. Password-protect it and tick "members-only page".
 */
?>
<!-- wp:tilden/backstage-hero {"align":"full"} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"40px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:40px"><!-- wp:tilden/backstage-signin /-->

<!-- wp:tilden/next-rehearsal /-->

<!-- wp:paragraph {"className":"tc-eyebrow"} -->
<p class="tc-eyebrow">Everyone</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"tc-cards","layout":{"type":"grid","columnCount":2,"minimumColumnWidth":null}} -->
<div class="wp-block-group tc-cards"><!-- wp:paragraph {"className":"tc-card tc-card--calendar"} -->
<p class="tc-card tc-card--calendar"><a href="/backstage/schedule/">Full cantata schedule</a><span>Every rehearsal, who it's for, what we'll cover, and which ones are required.</span></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"tc-card tc-card--play"} -->
<p class="tc-card tc-card--play"><a href="#listen">Hear the whole work</a><span>The publisher's recording, further down this page.</span></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:tilden/backstage-docs {"group":"everyone","headings":false} /-->

<!-- wp:paragraph {"className":"tc-eyebrow tc-eyebrow--singers"} -->
<p class="tc-eyebrow tc-eyebrow--singers">Singers</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"tc-tiles","layout":{"type":"grid","columnCount":5,"minimumColumnWidth":null}} -->
<div class="wp-block-group tc-tiles"><!-- wp:paragraph {"className":"tc-tile"} -->
<p class="tc-tile"><a href="/backstage/soprano/"><b>S</b><span>Soprano</span></a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"tc-tile"} -->
<p class="tc-tile"><a href="/backstage/alto/"><b>A</b><span>Alto</span></a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"tc-tile"} -->
<p class="tc-tile"><a href="/backstage/tenor/"><b>T</b><span>Tenor</span></a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"tc-tile"} -->
<p class="tc-tile"><a href="/backstage/bass/"><b>B</b><span>Bass</span></a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"tc-tile tc-tile--acc"} -->
<p class="tc-tile tc-tile--acc"><a href="/backstage/accompaniment/"><b>Acc</b><span>Accompaniment only</span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:tilden/backstage-docs {"group":"singers","headings":false} /-->

<!-- wp:tilden/backstage-docs {"group":"narrators"} /-->

<!-- wp:tilden/backstage-docs {"group":"actors"} /-->

<!-- wp:tilden/backstage-docs {"group":"tech"} /-->

<!-- wp:paragraph {"className":"tc-eyebrow"} -->
<p class="tc-eyebrow" id="listen">Hear the whole work</p>
<!-- /wp:paragraph -->

<!-- wp:tilden/cantata-listen /--></div>
<!-- /wp:group -->
