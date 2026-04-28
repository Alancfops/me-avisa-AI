# frozen_string_literal: true

module Acoes
  class MockCatalogService
    DATA = [
      {
        ticker: "PETR4",
        name: "Petrobras PN",
        price: 32.10,
        change_percent: -5.4,
        accumulated_change_5d: -11.2,
        pe_ratio: 4.8,
        dy_ttm: 14.1,
        sector: "Energia",
        price_target: 31.5,
        average_price_user: 33.4,
        last_alert_at: (Time.current - 28.hours).iso8601
      },
      {
        ticker: "ITSA4",
        name: "Itausa PN",
        price: 10.45,
        change_percent: 3.4,
        accumulated_change_5d: 2.1,
        pe_ratio: 8.2,
        dy_ttm: 8.3,
        sector: "Financeiro",
        price_target: 10.1,
        average_price_user: 9.8,
        last_alert_at: (Time.current - 2.hours).iso8601
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
