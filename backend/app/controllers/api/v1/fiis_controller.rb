# frozen_string_literal: true

module Api
  module V1
    class FiisController < ApplicationController
      def index
        render json: { data: catalog.list, source: "mock" }
      end

      def show
        fii = catalog.find_by_ticker(params[:ticker])
        return render_not_found("FII", params[:ticker]) if fii.nil?

        render json: { data: fii, source: "mock" }
      end

      private

      def catalog
        @catalog ||= Fiis::MockCatalogService.new
      end
    end
  end
end
