<?php
use Respect\Validation\Validator as DataValidator;
DataValidator::with('CustomValidations', true);

class CloseController extends Controller {
    const PATH = '/close';

    private $ticket;

    public function validations() {
        return [
            'permission' => 'user',
            'requestData' => [
                'ticketNumber' => [
                    'validation' => DataValidator::validTicketNumber(),
                    'error' => ERRORS::INVALID_TICKET
                ]
            ]
        ];
    }

    public function handler() {
        $this->ticket = Ticket::getByTicketNumber(Controller::request('ticketNumber'));

        if($this->shouldDenyPermission()) {
            Response::respondError(ERRORS::NO_PERMISSION);
            return;
        }

        $this->markAsUnread();
        $this->addCloseEvent();
        $this->ticket->closed = true;

        $this->ticket->store();
        Response::respondSuccess();
    }

    private function shouldDenyPermission() {
        $user = Controller::getLoggedUser();

        return (!Controller::isStaffLogged() && $this->ticket->author->id !== $user->id) ||
               (Controller::isStaffLogged() && $this->ticket->owner && $this->ticket->owner->id !== $user->id);
    }

    private function markAsUnread() {
        if(Controller::isStaffLogged()) {
            $this->ticket->unread = true;
        } else {
            $this->ticket->unreadStaff = true;
        }
    }

    private function addCloseEvent() {
        $event = Ticketevent::getEvent(Ticketevent::CLOSE);
        $event->setProperties(array(
            'date' => Date::getCurrentDate()
        ));

        if(Controller::isStaffLogged()) {
            $event->authorStaff = Controller::getLoggedUser();
        } else {
            $event->authorUser = Controller::getLoggedUser();
        }

        $this->ticket->addEvent($event);
    }
}