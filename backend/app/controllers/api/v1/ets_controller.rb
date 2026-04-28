# frozen_string_literal: true

module Api
  module V1
    class EtsController < ApplicationController
      def index
        render json: { data: catalog.list, source: "mock" }
      end

      def show
        etf = catalog.find_by_ticker(params[:ticker])
        return render_not_found("ETF", params[:ticker]) if etf.nil?

        render json: { data: etf, source: "mock" }
      end

      private

      def catalog
        @catalog ||= Ets::MockCatalogService.new
      end
    end
  end
end
