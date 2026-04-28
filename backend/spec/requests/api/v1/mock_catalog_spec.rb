# frozen_string_literal: true

require "rails_helper"

RSpec.describe "API V1 mock catalog", type: :request do
  describe "GET /api/v1/fiis" do
    it "returns mocked fiis list" do
      get "/api/v1/fiis"

      expect(response).to have_http_status(:ok)
      body = JSON.parse(response.body)
      expect(body["source"]).to eq("mock")
      expect(body["data"]).to be_an(Array)
      expect(body["data"].first["ticker"]).to eq("MXRF11")
    end
  end

  describe "GET /api/v1/fiis/:ticker" do
    it "returns one mocked fii by ticker" do
      get "/api/v1/fiis/mxrf11"

      expect(response).to have_http_status(:ok)
      body = JSON.parse(response.body)
      expect(body["data"]["ticker"]).to eq("MXRF11")
    end

    it "returns not found for unknown ticker" do
      get "/api/v1/fiis/XXXX11"

      expect(response).to have_http_status(:not_found)
    end
  end

  describe "GET /api/v1/acoes" do
    it "returns mocked stocks list" do
      get "/api/v1/acoes"

      expect(response).to have_http_status(:ok)
      body = JSON.parse(response.body)
      expect(body["data"].map { |asset| asset["ticker"] }).to include("PETR4")
    end
  end

  describe "GET /api/v1/ets" do
    it "returns mocked etf list" do
      get "/api/v1/ets"

      expect(response).to have_http_status(:ok)
      body = JSON.parse(response.body)
      expect(body["data"].map { |asset| asset["ticker"] }).to include("BOVA11")
    end
  end

  describe "GET /api/v1/alertas" do
    it "returns mocked alerts snapshot" do
      get "/api/v1/alertas"

      expect(response).to have_http_status(:ok)
      body = JSON.parse(response.body)
      expect(body["source"]).to eq("mock")
      expect(body["data"]).to be_an(Array)
      expect(body["data"].first).to include("ticker", "type", "reasons")
    end
  end
end
