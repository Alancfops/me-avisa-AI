# frozen_string_literal: true

module Fiis
  class MockCatalogService
    DATA = [
      {
        ticker: "MXRF11",
        name: "Maxi Renda",
        price: 9.55,
        change_percent: -1.2,
        p_vp: 0.98,
        dy_monthly: 1.1,
        dy_ttm: 12.6,
        average_price_30d: 10.2,
        price_target: 9.6,
        price_range: { min: 9.0, max: 9.6 },
        last_alert_at: (Time.current - 30.hours).iso8601
      },
      {
        ticker: "HGLG11",
        name: "CSHG Logistica",
        price: 154.3,
        change_percent: -3.5,
        p_vp: 0.89,
        dy_monthly: 0.85,
        dy_ttm: 10.2,
        average_price_30d: 164.0,
        price_target: nil,
        price_range: nil,
        last_alert_at: (Time.current - 3.hours).iso8601
      }
    ].freeze

    def list
      DATA
    end

    def find_by_ticker(ticker)
      normalized = normalize_ticker(ticker)
      DATA.find { |asset| asset[:ticker] == normalized }
    end

    private

    def normalize_ticker(ticker)
      ticker.to_s.strip.upcase
    end
  end
end
