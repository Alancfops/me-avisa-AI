# frozen_string_literal: true

module Alerts
  class SnapshotService
    COOLDOWN_HOURS = 24

    def call
      fiis_alerts + acoes_alerts + etfs_alerts
    end

    private

    def fiis_alerts
      Fiis::MockCatalogService.new.list.filter_map do |asset|
        reasons = []
        reasons << "alvo" if price_target_hit?(asset)
        reasons << "faixa" if inside_price_range?(asset)
        reasons << "automatico" if automatic_fii_rule?(asset)
        reasons << "queda_3_porcento" if asset[:change_percent].to_f < -3.0
        build_alert(asset, "fii", reasons) if reasons.any? && allowed_by_cooldown?(asset)
      end
    end

    def acoes_alerts
      Acoes::MockCatalogService.new.list.filter_map do |asset|
        reasons = []
        reasons << "alvo" if price_target_hit?(asset)
        reasons << "queda_diaria_5_porcento" if asset[:change_percent].to_f < -5.0
        reasons << "queda_acumulada_5_pregoes" if asset[:accumulated_change_5d].to_f < -10.0
        reasons << "abaixo_preco_medio" if below_average_price?(asset)
        reasons << "movimento_fora_padrao" if asset[:change_percent].to_f.abs > 3.0
        build_alert(asset, "acao", reasons) if reasons.any? && allowed_by_cooldown?(asset, strong_move_threshold: 7.0)
      end
    end

    def etfs_alerts
      Ets::MockCatalogService.new.list.filter_map do |asset|
        reasons = []
        reasons << "alvo" if price_target_hit?(asset)
        reasons << "queda_diaria_3_porcento" if asset[:change_percent].to_f < -3.0
        reasons << "queda_semanal_5_pregoes" if asset[:weekly_change_5d].to_f < -5.0
        reasons << "minima_52_semanas" if min_52_weeks_hit?(asset)
        build_alert(asset, "etf", reasons) if reasons.any? && allowed_by_cooldown?(asset, bypass_for_52_week_low: true)
      end
    end

    def build_alert(asset, type, reasons)
      {
        ticker: asset[:ticker],
        type: type,
        price: asset[:price],
        change_percent: asset[:change_percent],
        reasons: reasons,
        reference: alert_reference(asset)
      }
    end

    def alert_reference(asset)
      return "faixa #{asset[:price_range][:min]}-#{asset[:price_range][:max]}" if asset[:price_range]
      return "alvo #{asset[:price_target]}" if asset[:price_target]

      "regra automatica"
    end

    def price_target_hit?(asset)
      return false if asset[:price_target].nil?

      asset[:price].to_f <= asset[:price_target].to_f
    end

    def inside_price_range?(asset)
      range = asset[:price_range]
      return false unless range

      asset[:price].to_f >= range[:min].to_f && asset[:price].to_f <= range[:max].to_f
    end

    def automatic_fii_rule?(asset)
      return false if asset[:price_target] || asset[:price_range]

      asset[:price].to_f < (asset[:average_price_30d].to_f * 0.95) && asset[:p_vp].to_f < 1.0
    end

    def below_average_price?(asset)
      average_price_user = asset[:average_price_user]
      return false if average_price_user.nil?

      asset[:price].to_f < average_price_user.to_f
    end

    def min_52_weeks_hit?(asset)
      asset[:price].to_f <= asset[:min_52_weeks].to_f
    end

    def allowed_by_cooldown?(asset, strong_move_threshold: nil, bypass_for_52_week_low: false)
      last_alert_at = parse_time(asset[:last_alert_at])
      return true if last_alert_at.nil?

      cooldown_expired = last_alert_at <= COOLDOWN_HOURS.hours.ago
      return true if cooldown_expired

      return true if strong_move_threshold && asset[:change_percent].to_f.abs > strong_move_threshold
      return true if bypass_for_52_week_low && min_52_weeks_hit?(asset)

      false
    end

    def parse_time(raw_value)
      return nil if raw_value.nil?

      Time.zone.parse(raw_value)
    rescue ArgumentError
      nil
    end
  end
end
