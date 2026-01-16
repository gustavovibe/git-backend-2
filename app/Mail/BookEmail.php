<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;


class BookEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $orders;
    protected $data;
    protected $summaryValues;
    protected $invoice;
    protected $invoice_content;
    protected $flag;
		protected $template;

    public function __construct($orders,$data,$summaryValues,$invoice,$invoice_content,$flag, $template = 'emails.booking_confirmation_2')
    {
        $this->orders = $orders;
        $this->data = $data;
        $this->summaryValues = $summaryValues;
        $this->invoice = $invoice;
        $this->invoice_content = $invoice_content;
        $this->flag = $flag;
				$this->template = $template;
    }


    public function build()
    {
        $email = $this->subject('Your booking with Vibe Adventures')
                      ->view($this->template)
                      ->with([
                          'order' => $this->orders,
                      ]);

        // Adjuntar solo si 'data' no está vacío
        if ($this->data) {
            $pdf1 = Pdf::loadView('emails.tickets_booking', [
                
                'data' => $this->data['data'],
                'passengers_data' => $this->data['passengers_data']
            ]);
            $email->attachData($pdf1->output(), 'flight_tickets.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        if ($this->flag) {
            $pdf3 = Pdf::loadView('emails.invoice', $this->invoice_content);
            $email->attachData($pdf3->output(), 'invoice.pdf', ['mime' => 'application/pdf']);
        }
        if ($this->summaryValues) {
        // Adjuntar siempre el segundo PDF (si es requerido en todos los casos)
        $pdf2 = Pdf::loadView('emails.send_summary', [
            'tour' => $this->summaryValues['tour'],
            'countries_d' => $this->summaryValues['countries_d'],
            'services' => $this->summaryValues['services'],
        ]);
        $email->attachData($pdf2->output(), 'adventure_summary.pdf', [
            'mime' => 'application/pdf',
        ]);
        }

        return $email;
    }
}

