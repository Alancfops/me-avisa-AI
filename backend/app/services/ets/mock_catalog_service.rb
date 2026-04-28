# frozen_string_literal: true

module Ets
  class MockCatalogService
    DATA = [
      {
        ticker: "BOVA11",
        name: "iShares Ibovespa",
        price: 110.2,
        change_percent: -3.2,
        weekly_change_5d: -6.1,
        min_52_weeks: 108.8,
        reference_index: "Ibovespa",
        price_target: 108.0,
        last_alert_at: (Time.current - 10.hours).iso8601
      },
      {
        ticker: "IVVB11",
        name: "iShares S&P 500",
        price: 286.7,
        change_percent: -0.8,
        weekly_change_5d: -1.9,
        min_52_weeks: 240.0,
        reference_index: "S&P 500",
        price_target: 270.0,
        last_alert_at: (Time.current - 40.hours).iso8601
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
