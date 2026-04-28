# frozen_string_literal: true

module Api
  module V1
    class AcoesController < ApplicationController
      def index
        render json: { data: catalog.list, source: "mock" }
      end

      def show
        acao = catalog.find_by_ticker(params[:ticker])
        return render_not_found("Ação", params[:ticker]) if acao.nil?

        render json: { data: acao, source: "mock" }
      end

      private

      def catalog
        @catalog ||= Acoes::MockCatalogService.new
      end
    end
  end
end
