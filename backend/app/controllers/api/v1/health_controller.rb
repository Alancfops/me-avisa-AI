# frozen_string_literal: true

module Api
  module V1
    class HealthController < ApplicationController
      def show
        render json: { status: "ok", service: "me_avisa_api", version: "0.1.0" }
      end
    end
  end
end
