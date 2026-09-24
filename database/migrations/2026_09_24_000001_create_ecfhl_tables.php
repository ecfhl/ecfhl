<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('seasons', function (Blueprint $t) {
            $t->string('season_id')->primary(); $t->string('season_name'); $t->integer('sequence')->nullable();
            $t->string('league_id')->nullable(); $t->string('format')->nullable(); $t->boolean('cancelled')->default(false);
            $t->string('status')->nullable(); $t->text('note')->nullable(); $t->text('regular_source')->nullable();
            $t->text('playoff_source')->nullable(); $t->text('cancellation_source')->nullable();
            $t->decimal('entry_fee_paid_per_franchise',10,2)->nullable(); $t->string('placement_prizes_carried_to')->nullable();
            $t->decimal('winner_points',12,2)->nullable(); $t->decimal('runner_up_points',12,2)->nullable(); $t->string('source_id')->nullable();
        });
        Schema::create('franchises', function (Blueprint $t) { $t->string('franchise_id')->primary(); $t->string('franchise_name'); $t->boolean('active_in_2025_26')->default(false); $t->string('source_id')->nullable(); });
        Schema::create('franchise_aliases', function (Blueprint $t) { $t->string('alias_id')->primary(); $t->string('franchise_id')->index(); $t->string('alias_name'); $t->string('source_id')->nullable(); });
        Schema::create('season_members', function (Blueprint $t) { $t->string('season_member_id')->primary(); $t->string('season_id')->index(); $t->string('franchise_id')->index(); $t->string('source_id')->nullable(); });
        Schema::create('team_seasons', function (Blueprint $t) {
            $t->string('team_season_id')->primary(); $t->string('season_id')->index(); $t->string('franchise_id')->index(); $t->string('original_name');
            $t->integer('rank')->nullable(); $t->integer('w')->nullable(); $t->integer('l')->nullable(); $t->integer('t')->nullable();
            $t->decimal('standings_points',12,2)->nullable(); $t->decimal('fantasy_points_for',14,2)->nullable(); $t->decimal('fantasy_points_against',14,2)->nullable();
            $t->integer('player_games')->nullable(); $t->decimal('fantasy_points_per_player_game',12,4)->nullable(); $t->text('source')->nullable();
            $t->text('team_url')->nullable(); $t->string('source_id')->nullable();
        });
        Schema::create('players', function (Blueprint $t) { $t->string('player_id')->primary(); $t->string('player_name')->index(); });
        Schema::create('drafts', function (Blueprint $t) { $t->string('draft_id')->primary(); $t->string('season_id')->index(); $t->string('source_id')->nullable(); });
        Schema::create('draft_picks', function (Blueprint $t) {
            $t->string('draft_pick_id')->primary(); $t->string('draft_id')->index(); $t->string('franchise_id')->nullable()->index(); $t->string('team_name_raw')->nullable();
            $t->integer('round')->nullable(); $t->integer('pick_in_round')->nullable(); $t->integer('overall_pick')->nullable(); $t->string('player_id')->nullable()->index();
        });
        Schema::create('trades', function (Blueprint $t) {
            $t->string('trade_id')->primary(); $t->string('season_id')->index(); $t->string('source_trade_id')->nullable(); $t->string('from_franchise_id')->nullable()->index();
            $t->string('to_franchise_id')->nullable()->index(); $t->string('from_name_raw')->nullable(); $t->string('to_name_raw')->nullable(); $t->string('trade_datetime')->nullable();
            $t->string('trade_date_raw')->nullable(); $t->string('period')->nullable(); $t->string('status')->nullable(); $t->boolean('is_vetoed')->default(false);
            $t->boolean('is_reversed')->default(false); $t->boolean('executed_explicit')->nullable(); $t->integer('objection_count')->nullable(); $t->boolean('proposer_unknown')->nullable();
            $t->text('source')->nullable(); $t->string('source_id')->nullable();
        });
        Schema::create('trade_assets', function (Blueprint $t) {
            $t->string('trade_asset_id')->primary(); $t->string('trade_id')->index(); $t->string('source_side')->nullable(); $t->integer('item_order')->nullable();
            $t->string('from_franchise_id')->nullable()->index(); $t->string('to_franchise_id')->nullable()->index(); $t->string('asset_type')->nullable();
            $t->text('asset_description')->nullable(); $t->string('player_id')->nullable()->index(); $t->integer('draft_year')->nullable(); $t->integer('draft_round')->nullable();
            $t->string('pick_original_franchise_id')->nullable(); $t->string('pick_original_team_raw')->nullable(); $t->integer('contract_years_at_trade')->nullable();
        });
        Schema::create('award_types', function (Blueprint $t) { $t->string('award_type_id')->primary(); $t->string('award_name'); $t->string('recipient_type')->nullable(); });
        Schema::create('awards', function (Blueprint $t) {
            $t->string('award_id')->primary(); $t->string('season_id')->index(); $t->string('award_type_id')->index(); $t->string('franchise_id')->nullable()->index();
            $t->string('player_id')->nullable()->index(); $t->string('team_name_raw')->nullable(); $t->decimal('points',12,2)->nullable(); $t->text('basis')->nullable(); $t->string('source_id')->nullable();
        });
        Schema::create('playoff_rounds', function (Blueprint $t) { $t->string('playoff_round_id')->primary(); $t->string('season_id')->index(); $t->string('competition'); $t->string('round_name'); $t->integer('round_order')->nullable(); $t->integer('recorded_game_count')->nullable(); $t->string('source_id')->nullable(); });
        Schema::create('playoff_games', function (Blueprint $t) {
            $t->string('playoff_game_id')->primary(); $t->string('playoff_round_id')->index(); $t->integer('game_order')->nullable(); $t->string('team_a_franchise_id')->nullable();
            $t->string('team_a_name_raw')->nullable(); $t->decimal('team_a_score',12,2)->nullable(); $t->string('team_b_franchise_id')->nullable(); $t->string('team_b_name_raw')->nullable();
            $t->decimal('team_b_score',12,2)->nullable(); $t->boolean('is_bye')->default(false); $t->string('advancing_side_explicit')->nullable();
        });
        Schema::create('playoff_winners', function (Blueprint $t) { $t->string('playoff_winner_id')->primary(); $t->string('season_id')->index(); $t->string('competition'); $t->string('franchise_id')->nullable()->index(); $t->string('source_id')->nullable(); });
        Schema::create('prize_awards', function (Blueprint $t) { $t->string('prize_award_id')->primary(); $t->string('season_id')->index(); $t->string('franchise_id')->index(); $t->string('award_type_id')->index(); $t->integer('amount_cents'); $t->string('source_id')->nullable(); });
        Schema::create('season_prizes', function (Blueprint $t) { $t->string('season_id')->primary(); $t->integer('pot_cents')->nullable(); $t->integer('fees_paid_cents')->nullable(); $t->integer('credit_cents')->nullable(); $t->integer('distributed_cents')->nullable(); $t->string('source_id')->nullable(); });
        Schema::create('rules', function (Blueprint $t) { $t->string('rule_id')->primary(); $t->string('section')->nullable(); $t->string('subsection')->nullable(); $t->text('rule_text'); $t->string('source_id')->nullable(); });
    }

    public function down(): void {
        foreach (['rules','season_prizes','prize_awards','playoff_winners','playoff_games','playoff_rounds','awards','award_types','trade_assets','trades','draft_picks','drafts','players','team_seasons','season_members','franchise_aliases','franchises','seasons'] as $table) Schema::dropIfExists($table);
    }
};
