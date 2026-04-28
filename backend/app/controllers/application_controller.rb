class ApplicationController < ActionController::API
  private

  def render_not_found(resource, identifier)
    render json: { error: "#{resource} não encontrado", identifier: identifier }, status: :not_found
  end
end
