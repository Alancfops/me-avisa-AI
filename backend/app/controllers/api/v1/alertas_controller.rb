# frozen_string_literal: true

module Api
  module V1
    class AlertasController < ApplicationController
      def index
        render json: { data: alert_snapshot.call, source: "mock" }
      end

      private

      def alert_snapshot
        @alert_snapshot ||= Alerts::SnapshotService.new
      end
    end
  end
end
